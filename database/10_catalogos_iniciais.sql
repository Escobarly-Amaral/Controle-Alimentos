-- Catálogos globais e regra normativa. Execute após 00_schema.sql.

INSERT INTO permissao (id, nome, descricao, grupo) VALUES
('registrar_saida', 'Registrar saída', 'Registrar saídas de consumo, perda, vencimento e descarte.', 'operacao'),
('registrar_entrada', 'Registrar entrada', 'Registrar recebimentos: documento, lotes, validades e valores.', 'operacao'),
('cadastrar_produto', 'Cadastrar produtos e fornecedores', 'Criar, editar e arquivar produtos e fornecedores.', 'cadastro'),
('ajustar_estoque', 'Ajustar estoque', 'Registrar ajustes de inventário e estornos, sempre com motivo.', 'gestao'),
('revisar_pendencias', 'Revisar pendências', 'Analisar e encerrar divergências geradas pelo modo assistido.', 'gestao'),
('ver_relatorios', 'Ver relatórios', 'Acessar o painel de indicadores, relatórios e exportações.', 'consulta'),
('consultar', 'Consulta somente leitura', 'Visualizar estoque, movimentos e documentos sem alterar nada.', 'consulta'),
('gerir_usuarios', 'Gerir usuários', 'Criar usuários e conceder ou revogar permissões.', 'administracao')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), grupo = VALUES(grupo);

INSERT INTO configuracao_catalogo
(chave, nome, descricao, tipo_valor, valor_padrao, valores_permitidos, editavel_pela_unidade) VALUES
('modo_saida_excedente', 'Saída maior que o saldo', 'Define o tratamento de uma saída acima do saldo registrado.', 'opcao', 'assistido', JSON_ARRAY('assistido','estrito','livre'), 1),
('tolerancia_saida_excedente_pct', 'Tolerância de excedente (%)', 'Percentual de excedente que exige confirmação reforçada. A base de cálculo deve ser validada.', 'decimal', '10', JSON_OBJECT('min',0,'max',100), 1),
('janela_consumo_cobertura_dias', 'Janela de consumo (dias)', 'Período usado para calcular a média de consumo e a cobertura de estoque.', 'inteiro', '30', JSON_OBJECT('min',7,'max',365), 1),
('meta_interna_in_natura_pct', 'Meta interna de in natura (%)', 'Meta opcional da unidade, que não pode ser inferior ao mínimo da regra PNAE vigente.', 'decimal', NULL, JSON_OBJECT('minimo','regra_pnae.min_in_natura','max',100), 1)
ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), tipo_valor = VALUES(tipo_valor), valor_padrao = VALUES(valor_padrao), valores_permitidos = VALUES(valores_permitidos), editavel_pela_unidade = VALUES(editavel_pela_unidade);

INSERT INTO regra_pnae
(vigente_desde, vigente_ate, min_in_natura, max_proc_ultra, max_ingr_culinario, min_agric_familiar, referencia_normativa)
VALUES ('2026-03-02', NULL, 85.00, 10.00, 5.00, 45.00,
        'Resolução CD/FNDE nº 4/2026, art. 21; arts. 29 a 39 com alterações da Resolução CD/FNDE nº 17/2026')
ON DUPLICATE KEY UPDATE vigente_ate = VALUES(vigente_ate), min_in_natura = VALUES(min_in_natura), max_proc_ultra = VALUES(max_proc_ultra), max_ingr_culinario = VALUES(max_ingr_culinario), min_agric_familiar = VALUES(min_agric_familiar), referencia_normativa = VALUES(referencia_normativa);
