<?php 
$app = MapasCulturais\App::i();
$this->enqueueScript('app', 'aldirblanc', 'aldirblanc/app.js');

$slug = $plugin_validador->getSlug();
$name = $plugin_validador->getName();

$files = $entity->getFiles($slug); 
$url = $app->createUrl($slug, 'import', ['opportunity' => $entity->id]);
$template = '
<li id="file-{{id}}" class="widget-list-item">
    <a href="{{url}}" rel="noopener noreferrer">{{description}}</a> 
    <div class="botoes">
        <a href="'.$url.'?file={{id}}" class="btn btn-primary hltip js-validador-process" data-hltip-classes="hltip-ajuda" title="Clique para processar o arquivo enviado">processar</a>
        <a data-href="{{deleteUrl}}" data-target="#file-{{id}}" data-configm-message="Remover este arquivo?" class="icon icon-close hltip js-remove-item" data-hltip-classes="hltip-ajuda" title="Excluir arquivo" rel="noopener noreferrer"></a>
    </div>
</li>';
?>
<?= $entity->inciso ?>
<div class="widget">
    <h3 class="editando"><?= $name ?></h3>
    
    <div>
        <?php $this->part('validador-recursos/csv-button', ['opportunity' => $entity->id, 'plugin_aldirblanc' => $plugin_aldirblanc, 'plugin_validador' => $plugin_validador]); ?>
        <button class="btn btn-default add js-open-editbox hltip" data-target="#editbox-<?= $slug ?>-file" href="#" title="Clique para adicionar subir novo arquivo de recursos">Subir arquivo</button>
    </div>
    <div id="editbox-<?= $slug ?>-file" class="js-editbox mc-left" title="Subir arquivo de validação do <?= $name ?>" data-submit-label="Enviar">
        <?php $this->ajaxUploader($entity, $slug, 'append', 'ul.js-validador-recurso', $template, '', false, false, false)?>
    </div>
    <ul class="widget-list js-validador-recurso js-slimScroll">
        <?php if(is_array($files)): foreach($files as $file): ?>
            <li id="file-<?php echo $file->id ?>" class="widget-list-item<?php if($this->isEditable()) echo \MapasCulturais\i::_e(' is-editable'); ?>" >
                <a href="<?php echo $file->url;?>"><span><?php echo $file->description ? $file->description : $file->name;?></span></a>
                <?php if($processed_at = $entity->recurso_processed_files->{$file->name} ?? null): ?>
                    - processado em <?= $processed_at ?>
                <?php else: ?>
                <div class="botoes">
                    <a href="<?=$url?>?file=<?=$file->id?>" class="btn btn-primary hltip js-validador-process" data-hltip-classes="hltip-ajuda" title="Clique para processar o arquivo enviado">processar</a>
                    <a data-href="<?php echo $file->deleteUrl?>" data-target="#file-<?php echo $file->id ?>" data-configm-message="Remover este arquivo?" class="delete hltip js-remove-item" data-hltip-classes="hltip-ajuda" title="Excluir arquivo. Só é possível fazer esta ação antes do processamento."></a>
                </div>
                <?php endif; ?>
            
            </li>
        <?php endforeach; endif;?>
    </ul>
</div>
