<?php
$pagination = $pagination ?? null;
if (is_array($pagination) && $pagination['total'] > 0):
    $current=(int)$pagination['page'];$last=(int)$pagination['pages'];$visible=[1];
    for($n=max(2,$current-1);$n<=min($last-1,$current+1);$n++)$visible[]=$n;
    if($last>1)$visible[]=$last;$visible=array_values(array_unique($visible));sort($visible);
    $links=[];$previous=0;foreach($visible as $number){if($previous&&$number-$previous>1)$links[]=null;$links[]=$number;$previous=$number;}
    $pageParam=$paginationPageParam??'pagina';
    $baseQuery=$_GET;unset($baseQuery['pagina'],$baseQuery[$pageParam],$baseQuery['por_pagina']);
    $formQuery=$baseQuery;unset($formQuery['page']);
?>
<footer class="pagination-footer">
    <span class="pagination-count">Mostrando <?= (int)$pagination['from'] ?>–<?= (int)$pagination['to'] ?> de <?= (int)$pagination['total'] ?></span>
    <nav class="pagination-nav" aria-label="Paginação">
        <?php if($current<=1): ?><span class="pagination-button is-disabled" aria-disabled="true">Anterior</span><?php else: ?><a class="pagination-button" href="index.php?<?= e(http_build_query($baseQuery+[$pageParam=>$current-1,'por_pagina'=>$pagination['per_page']])) ?>">Anterior</a><?php endif; ?>
        <?php foreach($links as $number): ?><?php if($number===null): ?><span class="pagination-ellipsis" aria-hidden="true">…</span><?php else: ?><a class="pagination-number <?= $number===$current?'is-current':'' ?>" href="index.php?<?= e(http_build_query($baseQuery+[$pageParam=>$number,'por_pagina'=>$pagination['per_page']])) ?>" <?= $number===$current?'aria-current="page"':'' ?>><?= $number ?></a><?php endif; ?><?php endforeach; ?>
        <?php if($current>=$last): ?><span class="pagination-button is-disabled" aria-disabled="true">Próxima</span><?php else: ?><a class="pagination-button" href="index.php?<?= e(http_build_query($baseQuery+[$pageParam=>$current+1,'por_pagina'=>$pagination['per_page']])) ?>">Próxima</a><?php endif; ?>
    </nav>
    <form class="pagination-size" method="get"><input type="hidden" name="page" value="<?= e($_GET['page']??'') ?>"><?php foreach($formQuery as $key=>$value): ?><input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>"><?php endforeach; ?><input type="hidden" name="pagina" value="1"><label for="page-size">Por página</label><select id="page-size" name="por_pagina" onchange="this.form.submit()"><option value="10" <?= $pagination['per_page']===10?'selected':'' ?>>10</option><option value="20" <?= $pagination['per_page']===20?'selected':'' ?>>20</option><option value="50" <?= $pagination['per_page']===50?'selected':'' ?>>50</option></select><noscript><button class="workspace-button workspace-button-secondary" type="submit">Aplicar</button></noscript></form>
</footer>
<?php endif; ?>
