document.addEventListener('DOMContentLoaded', () => {
    const product = document.querySelector('[data-stock-product]');
    const unitLabel = document.querySelector('[data-selected-unit]');
    const balanceLabel = document.querySelector('[data-selected-balance]');
    const quantity = document.querySelector('[name="quantidade"]');
    const afterLabel = document.querySelector('[data-balance-after]');
    const overdrawFields = document.querySelector('[data-overdraw-fields]');
    const overdrawReason = document.querySelector('[name="motivo_excedente"]');

    const escapeHtml = (str) => {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    };

    const formatQty = (value, maxDecimals = 3) => {
        const num = Number(value);
        if (isNaN(num)) return '0';
        const rounded = Math.round((num + Number.EPSILON) * Math.pow(10, maxDecimals)) / Math.pow(10, maxDecimals);
        return rounded.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: maxDecimals });
    };

    const getSelectedProduct = () => {
        if (!product) return null;
        if (product.tagName === 'SELECT') {
            const opt = product.selectedOptions[0];
            if (!opt || !opt.value) return null;
            return {
                id: opt.value,
                name: opt.textContent.trim(),
                unit: opt.dataset.unit ?? 'unidade',
                balance: Math.round((Number(opt.dataset.balance ?? 0) + Number.EPSILON) * 1000) / 1000,
                fraction: opt.dataset.fraction === '1'
            };
        }
        if (!product.value) return null;
        return {
            id: product.value,
            name: product.dataset.name ?? '',
            unit: product.dataset.unit ?? 'unidade',
            balance: Math.round((Number(product.dataset.balance ?? 0) + Number.EPSILON) * 1000) / 1000,
            fraction: product.dataset.fraction === '1'
        };
    };

    // =========================================================
    // Combobox / Autocomplete de Produto com AJAX
    // =========================================================
    const combobox = document.querySelector('[data-product-combobox]');
    const searchInput = combobox?.querySelector('[data-product-search-input]');
    const clearBtn = combobox?.querySelector('[data-product-clear-btn]');
    const spinner = combobox?.querySelector('[data-product-spinner]');
    const dropdown = combobox?.querySelector('[data-product-dropdown]');
    const resultsList = combobox?.querySelector('[data-product-results]');
    const initialScript = combobox?.querySelector('[data-initial-products]');

    let initialProducts = [];
    if (initialScript) {
        try {
            initialProducts = JSON.parse(initialScript.textContent || '[]');
        } catch (_) {
            initialProducts = [];
        }
    }

    let searchTimeout = null;
    let currentResults = [];
    let focusedIndex = -1;

    const closeDropdown = () => {
        dropdown?.setAttribute('hidden', '');
        searchInput?.setAttribute('aria-expanded', 'false');
        focusedIndex = -1;
    };

    const selectProduct = (item) => {
        if (!product || !item) return;
        product.value = String(item.id);
        product.dataset.name = item.nome;
        product.dataset.unit = item.unidade_medida;
        product.dataset.balance = String(item.saldo);
        product.dataset.fraction = String(item.aceita_fracao);

        if (searchInput) {
            searchInput.value = item.nome;
            searchInput.setCustomValidity('');
        }
        if (clearBtn) {
            clearBtn.hidden = false;
        }
        closeDropdown();
        product.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const clearProduct = () => {
        if (!product) return;
        product.value = '';
        delete product.dataset.name;
        delete product.dataset.unit;
        delete product.dataset.balance;
        delete product.dataset.fraction;

        if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
        }
        if (clearBtn) {
            clearBtn.hidden = true;
        }
        closeDropdown();
        product.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const renderResults = (items, term = '') => {
        if (!resultsList) return;
        currentResults = items;
        focusedIndex = -1;
        resultsList.innerHTML = '';

        if (!items || items.length === 0) {
            const empty = document.createElement('li');
            empty.className = 'combobox-empty';
            empty.textContent = term ? `Nenhum produto encontrado para "${term}"` : 'Nenhum produto cadastrado com saldo';
            resultsList.appendChild(empty);
            dropdown?.removeAttribute('hidden');
            searchInput?.setAttribute('aria-expanded', 'true');
            return;
        }

        items.forEach((item, index) => {
            const li = document.createElement('li');
            li.className = 'combobox-item';
            if (String(product?.value) === String(item.id)) {
                li.classList.add('is-selected');
            }
            li.setAttribute('role', 'option');
            li.setAttribute('data-index', String(index));
            li.setAttribute('data-id', String(item.id));

            const isPositive = Number(item.saldo) > 0;
            const balanceClass = isPositive ? 'is-positive' : 'is-zero';
            const balanceText = isPositive 
                ? `Saldo: ${formatQty(item.saldo)} ${item.unidade_medida}`
                : `Sem saldo (0 ${item.unidade_medida})`;

            li.innerHTML = `
                <div class="combobox-item-main">
                    <strong>${escapeHtml(item.nome)}</strong>
                    <small>Unidade: ${escapeHtml(item.unidade_medida)}${item.aceita_fracao ? ' · Permite fração' : ''}</small>
                </div>
                <div class="combobox-item-balance">
                    <span class="balance-tag ${balanceClass}">${balanceText}</span>
                </div>
            `;

            li.addEventListener('click', () => {
                selectProduct(item);
            });

            resultsList.appendChild(li);
        });

        dropdown?.removeAttribute('hidden');
        searchInput?.setAttribute('aria-expanded', 'true');
    };

    const fetchProducts = async (term) => {
        if (spinner) spinner.hidden = false;
        try {
            const resp = await fetch(`index.php?page=saida-rapida&action=searchProducts&q=${encodeURIComponent(term)}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!resp.ok) throw new Error('Falha na resposta');
            const data = await resp.json();
            renderResults(data.products || [], term);
        } catch (err) {
            console.error('Erro na busca AJAX:', err);
            const lower = term.toLowerCase();
            const fallback = initialProducts.filter(p => p.nome.toLowerCase().includes(lower));
            renderResults(fallback, term);
        } finally {
            if (spinner) spinner.hidden = true;
        }
    };

    searchInput?.addEventListener('focus', () => {
        if (currentResults.length === 0) {
            renderResults(initialProducts.slice(0, 15));
        } else {
            dropdown?.removeAttribute('hidden');
            searchInput.setAttribute('aria-expanded', 'true');
        }
    });

    searchInput?.addEventListener('input', () => {
        if (searchInput) searchInput.setCustomValidity('');
        const term = searchInput.value.trim();
        if (clearBtn) clearBtn.hidden = term === '' && !product?.value;

        // Se o usuário apagar o texto digitado, limpa o hidden
        if (term === '' && product?.value) {
            product.value = '';
            delete product.dataset.name;
            delete product.dataset.unit;
            delete product.dataset.balance;
            delete product.dataset.fraction;
            product.dispatchEvent(new Event('change', { bubbles: true }));
        }

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            fetchProducts(term);
        }, 220);
    });

    const updateFocus = (items) => {
        items.forEach((item, idx) => {
            if (idx === focusedIndex) {
                item.classList.add('is-focused');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('is-focused');
            }
        });
    };

    searchInput?.addEventListener('keydown', (e) => {
        if (dropdown?.hasAttribute('hidden')) {
            if (e.key === 'ArrowDown' || e.key === 'Enter') {
                dropdown?.removeAttribute('hidden');
                searchInput.setAttribute('aria-expanded', 'true');
            }
            return;
        }

        const items = resultsList?.querySelectorAll('.combobox-item');
        if (!items || items.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            focusedIndex = (focusedIndex + 1) % items.length;
            updateFocus(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            focusedIndex = (focusedIndex - 1 + items.length) % items.length;
            updateFocus(items);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (focusedIndex >= 0 && focusedIndex < currentResults.length) {
                selectProduct(currentResults[focusedIndex]);
            }
        } else if (e.key === 'Escape') {
            closeDropdown();
        }
    });

    clearBtn?.addEventListener('click', clearProduct);

    const quickExitForm = document.querySelector('.quick-exit-form');
    quickExitForm?.addEventListener('submit', (e) => {
        if (!product?.value) {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
                searchInput.setCustomValidity('Por favor, selecione um produto da lista.');
                searchInput.reportValidity();
            }
            return false;
        }
    });

    document.addEventListener('click', (e) => {
        if (combobox && !combobox.contains(e.target)) {
            closeDropdown();
        }
    });

    const chooseProduct = (id) => {
        const strId = String(id);
        const found = initialProducts.find(p => String(p.id) === strId);
        if (found) {
            selectProduct(found);
            return;
        }
        fetch(`index.php?page=saida-rapida&action=searchProducts&q=`)
            .then(r => r.json())
            .then(data => {
                const item = (data.products || []).find(p => String(p.id) === strId);
                if (item) selectProduct(item);
            })
            .catch(() => {});
    };

    document.querySelectorAll('[data-use-product]').forEach((button) => button.addEventListener('click', () => chooseProduct(button.dataset.useProduct)));

    document.querySelectorAll('[data-qty-step]').forEach((button) => button.addEventListener('click', () => {
        if (!quantity) return;
        const current = Number(quantity.value || 0);
        const step = Number(button.dataset.qtyStep);
        const p = getSelectedProduct();
        const canFraction = p ? p.fraction : true;
        let nextVal;

        if (step < 0) {
            if (canFraction) {
                if (current > 1) {
                    nextVal = Math.max(1, Math.round(current) - 1);
                } else if (current > 0.5) {
                    nextVal = 0.5;
                } else if (current > 0.25) {
                    nextVal = 0.25;
                } else if (current > 0.1) {
                    nextVal = 0.1;
                } else {
                    nextVal = 0.1;
                }
            } else {
                nextVal = Math.max(1, Math.round(current) - 1);
            }
        } else {
            if (canFraction && current < 1) {
                if (current < 0.25) nextVal = 0.25;
                else if (current < 0.5) nextVal = 0.5;
                else nextVal = 1;
            } else {
                nextVal = Math.floor(current + Number.EPSILON) + 1;
            }
        }

        nextVal = Math.round((nextVal + Number.EPSILON) * 1000) / 1000;
        quantity.value = String(Math.max(canFraction ? 0.001 : 1, nextVal));
        quantity.dispatchEvent(new Event('input', { bubbles: true }));
    }));

    document.querySelectorAll('[data-qty-set]').forEach((button) => button.addEventListener('click', () => {
        if (!quantity) return;
        quantity.value = button.dataset.qtySet;
        quantity.dispatchEvent(new Event('input', { bubbles: true }));
    }));

    const updateAfter = () => {
        if (!afterLabel || !product) return;
        const p = getSelectedProduct();
        const balance = p?.balance ?? 0;
        const amount = Math.round((Number(quantity?.value ?? 0) + Number.EPSILON) * 1000) / 1000;
        const diff = Math.round((balance - amount + Number.EPSILON) * 1000) / 1000;
        const unit = p?.unit ?? '';

        if (p) {
            afterLabel.textContent = `Saldo ficará: ${formatQty(Math.max(0, diff))} ${unit}`;
        } else {
            afterLabel.textContent = 'Saldo ficará: —';
        }

        const exceeds = Boolean(p) && amount > balance;
        afterLabel.classList.toggle('is-warning', exceeds);
        if (overdrawFields) overdrawFields.hidden = !exceeds;
        if (overdrawReason) overdrawReason.required = exceeds;
        if (exceeds) {
            afterLabel.textContent = `Saldo registrado: ${formatQty(balance)} ${unit}. Você está retirando ${formatQty(amount)} ${unit}. Será criada uma pendência para revisão.`;
        }
    };

    product?.addEventListener('change', () => {
        const p = getSelectedProduct();
        const unit = p?.unit ?? 'unidade';
        if (unitLabel) unitLabel.textContent = unit;
        if (balanceLabel) {
            balanceLabel.textContent = p
                ? `Saldo registrado: ${formatQty(p.balance)} ${unit}. O lote com validade mais próxima será sugerido primeiro.`
                : 'O lote com validade mais próxima será sugerido primeiro.';
        }
        if (quantity) {
            const canFraction = p ? p.fraction : true;
            quantity.step = canFraction ? 'any' : '1';
            quantity.min = canFraction ? '0.001' : '1';
        }
        updateAfter();
    });
    quantity?.addEventListener('input', updateAfter);

    const docType = document.querySelector('[name="tipo_documento"]');
    const resource = document.querySelector('[data-resource]');
    const updateEntryFields = () => {
        const donation = resource?.value === 'doacao';
        document.querySelectorAll('[data-purchase-field]').forEach((field) => {
            field.hidden = donation;
            field.querySelectorAll('input,select').forEach((input) => { input.disabled = donation; });
        });
        document.querySelectorAll('[data-donation-field]').forEach((field) => {
            field.hidden = !donation;
            field.querySelectorAll('input').forEach((input) => { input.required = donation; input.disabled = !donation; });
        });
        const pnaeField = document.querySelector('[data-pnae-field]');
        if (pnaeField) { pnaeField.hidden = resource?.value !== 'pnae_federal'; pnaeField.querySelector('input').disabled = resource?.value !== 'pnae_federal'; }
        if (docType) docType.closest('.workspace-field').toggleAttribute('data-doacao-tipo', docType.value === 'termo_doacao');
        updateEntrySummary();
    };
    docType?.addEventListener('change', updateEntryFields);
    resource?.addEventListener('change', updateEntryFields);

    const upload = document.querySelector('[name="anexo"][type="file"]');
    upload?.addEventListener('change', () => {
        const label = document.querySelector('[data-upload-name]');
        if (label) label.textContent = upload.files?.[0]?.name ?? '';
    });

    const formatMoney = (amount) => {
        return Number(amount || 0).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    };

    const entryForm = document.querySelector('[data-entry-form]');
    const entrySummary = document.querySelector('.entry-summary');

    const updateEntrySummary = () => {
        if (!entrySummary) return;

        const isDonation = resource?.value === 'doacao';
        const statusBadge = entrySummary.querySelector('[data-entry-reconcile]');
        const statusText = entrySummary.querySelector('[data-entry-reconcile-text]');
        const statusIcon = statusBadge?.querySelector('.material-symbols-outlined');

        if (isDonation) {
            entrySummary.classList.add('is-donation');
            if (statusBadge && statusText && statusIcon) {
                statusBadge.className = 'entry-status-badge is-info';
                statusIcon.textContent = 'volunteer_activism';
                statusText.textContent = 'Entrada por doação (sem valor financeiro)';
            }
            return;
        }
        entrySummary.classList.remove('is-donation');

        const docTotalInput = document.querySelector('[data-document-total]');
        const rawDocVal = parseFloat(String(docTotalInput?.value ?? '').replace(',', '.')) || 0;
        const docTotal = Math.round((rawDocVal + Number.EPSILON) * 100) / 100;

        let itemsSum = 0;
        let hasItemValues = false;
        document.querySelectorAll('[name$="[valor_item]"]').forEach((input) => {
            if (!input.disabled && input.value !== '') {
                const itemVal = parseFloat(String(input.value).replace(',', '.')) || 0;
                itemsSum += itemVal;
                hasItemValues = true;
            }
        });
        itemsSum = Math.round((itemsSum + Number.EPSILON) * 100) / 100;

        const diff = Math.round((Math.abs(itemsSum - docTotal) + Number.EPSILON) * 100) / 100;

        const sumEl = entrySummary.querySelector('[data-entry-items-sum]');
        const totalEl = entrySummary.querySelector('[data-entry-doc-total]');
        const diffEl = entrySummary.querySelector('[data-entry-diff]');

        if (sumEl) sumEl.textContent = formatMoney(itemsSum);
        if (totalEl) totalEl.textContent = formatMoney(docTotal);
        if (diffEl) diffEl.textContent = formatMoney(diff);

        if (!statusBadge || !statusText || !statusIcon) return;

        if (docTotal === 0 && !hasItemValues) {
            statusBadge.className = 'entry-status-badge is-neutral';
            statusIcon.textContent = 'hourglass_empty';
            statusText.textContent = 'Aguardando valores';
        } else if (diff <= 0.01 && (docTotal > 0 || hasItemValues)) {
            statusBadge.className = 'entry-status-badge is-success';
            statusIcon.textContent = 'check_circle';
            statusText.textContent = 'Valores conferem';
        } else if (itemsSum < docTotal) {
            statusBadge.className = 'entry-status-badge is-warning';
            statusIcon.textContent = 'warning';
            statusText.textContent = `Faltam ${formatMoney(diff)} nos itens`;
        } else {
            statusBadge.className = 'entry-status-badge is-danger';
            statusIcon.textContent = 'error';
            statusText.textContent = `Itens excedem a nota em ${formatMoney(diff)}`;
        }
    };

    updateEntryFields();
    updateEntrySummary();

    entryForm?.addEventListener('input', (event) => {
        if (event.target.matches('[data-document-total], [name$="[valor_item]"]')) {
            updateEntrySummary();
        }
    });

    // =========================================================
    // Combobox / Autocomplete de Produto em Entrada por Documento
    // =========================================================
    const entryInitialScript = document.querySelector('[data-entry-initial-products]');
    let entryInitialProducts = [];
    if (entryInitialScript) {
        try {
            entryInitialProducts = JSON.parse(entryInitialScript.textContent || '[]');
        } catch (_) {
            entryInitialProducts = [];
        }
    }

    const initEntryItemCombobox = (fieldset) => {
        if (!fieldset) return;
        const itemCombobox = fieldset.querySelector('[data-item-combobox]');
        if (!itemCombobox || itemCombobox.dataset.initialized) return;
        itemCombobox.dataset.initialized = 'true';

        const hiddenInput = itemCombobox.querySelector('[data-item-product]');
        const searchInput = itemCombobox.querySelector('[data-item-search-input]');
        const clearBtn = itemCombobox.querySelector('[data-item-clear-btn]');
        const spinner = itemCombobox.querySelector('[data-item-spinner]');
        const dropdown = itemCombobox.querySelector('[data-item-dropdown]');
        const resultsList = itemCombobox.querySelector('[data-item-results]');
        const validityInput = fieldset.querySelector('[data-item-validity]');

        let itemTimeout = null;
        let itemResults = [];
        let itemFocusIndex = -1;

        const closeDropdown = () => {
            dropdown?.setAttribute('hidden', '');
            searchInput?.setAttribute('aria-expanded', 'false');
            itemFocusIndex = -1;
        };

        const selectItemProduct = (p) => {
            if (!hiddenInput || !p) return;
            hiddenInput.value = String(p.id);
            hiddenInput.dataset.name = p.nome;
            hiddenInput.dataset.unit = p.unidade_medida;
            hiddenInput.dataset.validityDays = String(p.validade_padrao_dias ?? '');

            if (searchInput) {
                searchInput.value = `${p.nome} (${p.unidade_medida})`;
                searchInput.setCustomValidity('');
            }
            if (clearBtn) {
                clearBtn.hidden = false;
            }

            // Sugestão automática de validade se houver dias padrão e o campo estiver vazio
            if (validityInput && !validityInput.value && p.validade_padrao_dias) {
                const targetDate = new Date();
                targetDate.setDate(targetDate.getDate() + Number(p.validade_padrao_dias));
                validityInput.value = targetDate.toISOString().split('T')[0];
            }

            closeDropdown();
            hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
        };

        const clearItemProduct = () => {
            if (!hiddenInput) return;
            hiddenInput.value = '';
            delete hiddenInput.dataset.name;
            delete hiddenInput.dataset.unit;
            delete hiddenInput.dataset.validityDays;

            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }
            if (clearBtn) {
                clearBtn.hidden = true;
            }
            closeDropdown();
            hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
        };

        const renderItemResults = (items, term = '') => {
            if (!resultsList) return;
            itemResults = items;
            itemFocusIndex = -1;
            resultsList.innerHTML = '';

            if (!items || items.length === 0) {
                const empty = document.createElement('li');
                empty.className = 'combobox-empty';
                empty.textContent = term ? `Nenhum produto encontrado para "${term}"` : 'Nenhum produto disponível';
                resultsList.appendChild(empty);
                dropdown?.removeAttribute('hidden');
                searchInput?.setAttribute('aria-expanded', 'true');
                return;
            }

            items.forEach((item) => {
                const li = document.createElement('li');
                li.className = 'combobox-item';
                if (String(hiddenInput?.value) === String(item.id)) {
                    li.classList.add('is-selected');
                }
                li.setAttribute('role', 'option');

                const validityText = item.validade_padrao_dias 
                    ? ` · Validade padrão: ${item.validade_padrao_dias} dias` 
                    : '';

                li.innerHTML = `
                    <div class="combobox-item-main">
                        <strong>${escapeHtml(item.nome)}</strong>
                        <small>Unidade: ${escapeHtml(item.unidade_medida)}${validityText}</small>
                    </div>
                `;

                li.addEventListener('click', () => {
                    selectItemProduct(item);
                });

                resultsList.appendChild(li);
            });

            dropdown?.removeAttribute('hidden');
            searchInput?.setAttribute('aria-expanded', 'true');
        };

        const fetchItemProducts = async (term) => {
            if (spinner) spinner.hidden = false;
            try {
                const resp = await fetch(`index.php?page=entrada-documento&action=searchProducts&q=${encodeURIComponent(term)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!resp.ok) throw new Error('Falha na resposta');
                const data = await resp.json();
                renderItemResults(data.products || [], term);
            } catch (err) {
                console.error('Erro na busca de produtos da entrada:', err);
                const lower = term.toLowerCase();
                const fallback = entryInitialProducts.filter(p => p.nome.toLowerCase().includes(lower));
                renderItemResults(fallback, term);
            } finally {
                if (spinner) spinner.hidden = true;
            }
        };

        searchInput?.addEventListener('focus', () => {
            if (itemResults.length === 0) {
                renderItemResults(entryInitialProducts.slice(0, 15));
            } else {
                dropdown?.removeAttribute('hidden');
                searchInput.setAttribute('aria-expanded', 'true');
            }
        });

        searchInput?.addEventListener('input', () => {
            if (searchInput) searchInput.setCustomValidity('');
            const term = searchInput.value.trim();
            if (clearBtn) clearBtn.hidden = term === '' && !hiddenInput?.value;

            if (term === '' && hiddenInput?.value) {
                hiddenInput.value = '';
                delete hiddenInput.dataset.name;
                delete hiddenInput.dataset.unit;
                delete hiddenInput.dataset.validityDays;
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            clearTimeout(itemTimeout);
            itemTimeout = setTimeout(() => {
                fetchItemProducts(term);
            }, 220);
        });

        searchInput?.addEventListener('keydown', (e) => {
            if (dropdown?.hasAttribute('hidden')) {
                if (e.key === 'ArrowDown' || e.key === 'Enter') {
                    dropdown?.removeAttribute('hidden');
                    searchInput.setAttribute('aria-expanded', 'true');
                }
                return;
            }

            const items = resultsList?.querySelectorAll('.combobox-item');
            if (!items || items.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                itemFocusIndex = (itemFocusIndex + 1) % items.length;
                items.forEach((item, idx) => {
                    if (idx === itemFocusIndex) {
                        item.classList.add('is-focused');
                        item.scrollIntoView({ block: 'nearest' });
                    } else {
                        item.classList.remove('is-focused');
                    }
                });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                itemFocusIndex = (itemFocusIndex - 1 + items.length) % items.length;
                items.forEach((item, idx) => {
                    if (idx === itemFocusIndex) {
                        item.classList.add('is-focused');
                        item.scrollIntoView({ block: 'nearest' });
                    } else {
                        item.classList.remove('is-focused');
                    }
                });
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (itemFocusIndex >= 0 && itemFocusIndex < itemResults.length) {
                    selectItemProduct(itemResults[itemFocusIndex]);
                }
            } else if (e.key === 'Escape') {
                closeDropdown();
            }
        });

        clearBtn?.addEventListener('click', clearItemProduct);

        document.addEventListener('click', (e) => {
            if (itemCombobox && !itemCombobox.contains(e.target)) {
                closeDropdown();
            }
        });
    };

    // Inicializa todos os itens de entrada existentes
    document.querySelectorAll('.entry-line').forEach(initEntryItemCombobox);

    const lines = document.querySelector('[data-entry-lines]');
    const addLine = document.querySelector('[data-add-entry-line]');
    const reindexLines = () => {
        if (!lines) return;
        lines.querySelectorAll('.entry-line').forEach((fieldset, index) => {
            const legend = fieldset.querySelector('legend');
            if (legend) legend.textContent = `Item ${index + 1}`;
            fieldset.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace(/itens\[\d+\]/, `itens[${index}]`);
            });
        });
        updateEntrySummary();
    };
    addLine?.addEventListener('click', () => {
        if (!lines) return;
        const index = lines.querySelectorAll('.entry-line').length;
        const fieldset = document.createElement('fieldset');
        fieldset.className = 'entry-line';
        fieldset.setAttribute('data-entry-line-item', '');
        fieldset.innerHTML = `
            <legend>Item ${index + 1}</legend>
            <div class="workspace-field entry-product-field">
                <span>Produto</span>
                <div class="entry-product-combobox" data-item-combobox>
                    <input type="hidden" name="itens[${index}][produto_id]" data-item-product value="">
                    <div class="combobox-input-wrap">
                        <span class="material-symbols-outlined combobox-icon" aria-hidden="true">search</span>
                        <input type="text"
                               class="combobox-search-input"
                               data-item-search-input
                               placeholder="Buscar produto..."
                               autocomplete="off"
                               aria-expanded="false"
                               aria-haspopup="listbox"
                               role="combobox">
                        <button type="button" class="combobox-clear-btn" data-item-clear-btn aria-label="Limpar produto" hidden title="Limpar seleção">
                            <span class="material-symbols-outlined" aria-hidden="true">close</span>
                        </button>
                        <span class="combobox-spinner" data-item-spinner hidden aria-hidden="true"></span>
                    </div>
                    <div class="combobox-dropdown" data-item-dropdown hidden role="listbox">
                        <ul class="combobox-results" data-item-results></ul>
                    </div>
                </div>
            </div>
            <label class="workspace-field"><span>Quantidade</span><input name="itens[${index}][quantidade]" type="number" min="0.001" step="any" inputmode="decimal"></label>
            <label class="workspace-field"><span>Validade</span><input name="itens[${index}][validade]" type="date" data-item-validity></label>
            <label class="workspace-field"><span>Código do lote</span><input name="itens[${index}][codigo_lote]" maxlength="100"></label>
            <label class="workspace-field" data-purchase-field><span>Valor do lote (R$)</span><input name="itens[${index}][valor_item]" type="number" min="0" step="0.01" inputmode="decimal"></label>
            <label class="workspace-check"><input name="itens[${index}][validade_estimada]" type="checkbox" value="1"><span>Validade estimada</span></label>
            <button class="entry-remove" type="button" data-remove-entry-line><span class="material-symbols-outlined" aria-hidden="true">delete</span>Remover item</button>
        `;
        lines.append(fieldset);
        initEntryItemCombobox(fieldset);
        reindexLines();
        updateEntryFields();
    });
    lines?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-entry-line]');
        if (!button) return;
        button.closest('.entry-line')?.remove();
        reindexLines();
    });

    entryForm?.addEventListener('submit', (e) => {
        const itemRows = entryForm.querySelectorAll('.entry-line');
        let hasAnyValidItem = false;
        let missingProductRow = null;

        itemRows.forEach((row) => {
            const prodInput = row.querySelector('[data-item-product]');
            const qtyInput = row.querySelector('[name*="[quantidade]"]');
            const searchInp = row.querySelector('[data-item-search-input]');

            const hasQty = parseFloat(qtyInput?.value) > 0;
            const hasProduct = Boolean(prodInput?.value);

            if (hasProduct) {
                hasAnyValidItem = true;
            } else if (hasQty || (searchInp && searchInp.value.trim() !== '')) {
                if (!missingProductRow) missingProductRow = searchInp;
            }
        });

        if (missingProductRow) {
            e.preventDefault();
            missingProductRow.focus();
            missingProductRow.setCustomValidity('Por favor, selecione um produto da lista de sugestões.');
            missingProductRow.reportValidity();
            return false;
        }

        if (!hasAnyValidItem) {
            e.preventDefault();
            const firstSearch = entryForm.querySelector('[data-item-search-input]');
            if (firstSearch) {
                firstSearch.focus();
                firstSearch.setCustomValidity('Adicione e selecione ao menos um produto para registrar a entrada.');
                firstSearch.reportValidity();
            }
            return false;
        }
    });

    document.querySelectorAll('[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });
});
