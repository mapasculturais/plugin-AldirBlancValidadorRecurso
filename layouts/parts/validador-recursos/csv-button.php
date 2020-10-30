<?php 
use MapasCulturais\i;

$app = MapasCulturais\App::i();

$slug = $plugin_validador->getSlug();
$name = $plugin_validador->getName();

$route = MapasCulturais\App::i()->createUrl($slug, 'export');    
?>

<a class="btn btn-default download btn-export-cancel"  ng-click="editbox.open('<?= $slug ?>-editbox', $event)" rel="noopener noreferrer"><?php i::esc_attr_e('Baixar template') ?></a>

<!-- Formulário -->
<edit-box id="<?= $slug ?>-editbox" position="left" title="<?php i::esc_attr_e('Baixar planilha modelo para recursos') ?>" cancel-label="Cancelar" close-on-cancel="true">
    <form class="form-export-<?= $slug ?>" action="<?=$route?>" method="POST">
    
        <label for="financeiro-from">Data inícial</label>
        <input type="date" name="from" id="financeiro-from">
        
        <label for="financeiro-to">Data final</label>  
        <input type="date" name="to" id="financeiro-to">

        <input type="hidden" name="opportunity" value="<?=$opportunity?>">
        
        # Caso não queira filtrar entre datas, deixe os campos vazios.
        <button class="btn btn-primary download" type="submit" style="float: right; margin-left:1em">Exportar</button>
    </form>
</edit-box>
