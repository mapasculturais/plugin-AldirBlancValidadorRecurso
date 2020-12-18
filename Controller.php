<?php

namespace AldirBlancValidadorRecurso;

use DateTime;
use Doctrine\ORM\ORMException;
use Exception;
use InvalidArgumentException;
use League\Csv\Writer;
use League\Csv\Reader;
use League\Csv\Statement;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Registration;
use MapasCulturais\Entities\RegistrationEvaluation;
use RegistrationPayments\Payment;

/**
 * Registration Controller
 *
 * By default this controller is registered with the id 'registration'.
 *
 *  @property-read \MapasCulturais\Entities\Registration $requestedEntity The Requested Entity
 */
// class AldirBlanc extends \MapasCulturais\Controllers\EntityController {
class Controller extends \MapasCulturais\Controllers\Registration
{
    protected $config = [];

    protected $instanceConfig = [];

    protected $columns = [
        'NUMERO',
        'STATUS',
        'OBSERVACOES'
    ];

    /**
     * @var Plugin
     */
    protected $plugin;

    public function setPlugin(Plugin $plugin)
    {
        $this->plugin = $plugin;
        
        $app = App::i();

        $this->config = $app->plugins['AldirBlanc']->config;
        $this->config += $this->plugin->config;
    }

    protected function exportInit(Opportunity $opportunity) {
        $this->requireAuthentication();

        if (!$opportunity->canUser('@control')) {
            echo "Não autorizado";
            die();
        }

        //Seta o timeout
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '768M');

    }

    protected function generateCSV(array $registrations):string {
        /**
         * Array com header do documento CSV
         * @var array $headers
         */
        $headers = $this->columns;
        
        $csv_data = [];

        foreach ($registrations as $i => $registration) {
            $csv_data[$i] = [
                'NUMERO' => $registration->number,
                'STATUS' => null,
                'OBSERVACOES' => null,
            ];
        }

        $validador = $this->plugin->getSlug();
        $hash = md5(json_encode($csv_data));

        $dir = PRIVATE_FILES_PATH . 'aldirblanc/';

        $filename =  $dir . "{$validador}-{$hash}.csv";

        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $stream = fopen($filename, 'w');

        $csv = Writer::createFromStream($stream);
        $csv->setDelimiter(";");

        $csv->insertOne($headers);

        foreach ($csv_data as $csv_line) {
            $csv->insertOne($csv_line);
        }

        return $filename;
    }

    /**
     * Exportador 
     *
     * Implementa o sistema de exportação para a lei AldirBlanc
     * http://localhost:8080/{$slug}/export/status:1/from:2020-01-01/to:2020-01-30
     *
     * Parâmetros to e from não são obrigatórios, caso não informado retorna todos os registros no status de pendentes
     *
     * Parâmetro status não é obrigatório, caso não informado retorna todos com status 1
     *
     */
    public function ALL_export()
    {
        $app = App::i();

        //Oportunidade que a query deve filtrar
        $opportunity_id = $this->data['opportunity'];
        $opportunity = $app->repo('Opportunity')->find($opportunity_id);

        $this->exportInit($opportunity);

        $filename = $this->generateCSV([]);

        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename=' . basename($filename));
        header('Pragma: no-cache');
        readfile($filename);
    }

    public function GET_import() {
        $this->requireAuthentication();

        $app = App::i();

        $opportunity_id = $this->data['opportunity'] ?? 0;
        $file_id = $this->data['file'] ?? 0;

        $config = $app->plugins['AldirBlanc']->config;

        $lab_opportunity_ids = array_merge(
            [$config['inciso1_opportunity_id']],
            $config['inciso2_opportunity_ids'],
            $config['inciso3_opportunity_ids']
        );

        if(!in_array($opportunity_id, $lab_opportunity_ids)){
            echo "Opportunidade de id $opportunity_id não é da Lei Aldir Blanc";
            die;
        }

        $opportunity = $app->repo('Opportunity')->find($opportunity_id);

        if (!$opportunity) {
            echo "Opportunidade de id $opportunity_id não encontrada";
            die;
        }

        $opportunity->checkPermission('@control');

        $files = $opportunity->getFiles($this->plugin->getSlug());
        
        foreach ($files as $file) {
            if ($file->id == $file_id) {
                $this->import($opportunity, $file->getPath());
            }
        }
    }

    /**
     * Importador para o inciso 1
     *
     * http://localhost:8080/{slug}/import/
     *
     */
    public function import(Opportunity $opportunity, string $filename)
    {

        /**
         * Seta o timeout e limite de memoria
         */
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '768M');


        //verifica se o mesmo esta no servidor
        if (!file_exists($filename)) {
            throw new Exception("Erro ao processar o arquivo. Arquivo inexistente");
        }

        $app = App::i();

        //Abre o arquivo em modo de leitura
        $stream = fopen($filename, "r");

        //Faz a leitura do arquivo
        $csv = Reader::createFromStream($stream);

        //Define o limitador do arqivo (, ou ;)
        $csv->setDelimiter(";");

        //Seta em que linha deve se iniciar a leitura
        $header_temp = $csv->setHeaderOffset(0);
        
        //Faz o processamento dos dados
        $stmt = (new Statement());
        $results = $stmt->process($csv);

        //Verifica a extenção do arquivo
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        if ($ext != "csv") {
            throw new Exception("Arquivo não permitido.");
        }
        
        $header_file = [];
        foreach ($header_temp as $key => $value) {
            $header_file[] = $value;
            break;
        }
        $columns = '"' . implode('", "', $this->columns) . '"';

        foreach ($this->columns as $column) {
            if (!isset($header_file[0][$column])) {
                die("As colunas {$columns} são obrigatórias");
            }
        }

        $user = $this->plugin->getUser();

        $slug = $this->plugin->slug;
        $name = $this->plugin->name;
        
        $app->disableAccessControl();
        $count = 0;
        foreach ($results as $i => $line) {
            $num = $line['NUMERO'];
            $obs = $line['OBSERVACOES'];
            $eval = $line['STATUS'];

            $obs_padrao = '';

            switch(strtolower($eval)){
                case 'homologado':
                case 'homologada':
                    $result = $this->config['result_homologada'];
                    $obs_padrao = 'Recurso deferido';
                break;

                case 'em analise':
                case 'em análise':
                case 'analise':
                case 'análise':
                case 'recebido':
                case 'recebida':
                    $result = $this->config['result_analise'];
                    $obs_padrao = 'Recurso recebido e em análise';
                break;
            
                case 'deferido':
                case 'deferida':
                case 'aprovado':
                case 'aprovada':
                case 'selecionado':
                case 'selecionada':
                    $result = $this->config['result_selecionada'];
                    $obs_padrao = 'Recurso deferido';
                break;

                case 'negada':
                case 'negado':
                case 'invalido':
                case 'inválido':
                case 'invalida':
                case 'inválida':
                    $result = $this->config['result_invalida'];
                    $obs_padrao = 'Recurso negado';
                break;

                case 'indeferido':
                case 'indeferida':
                case 'não selecionado':
                case 'nao selecionado':
                case 'não selecionada':
                case 'nao selecionada':
                    $result = $this->config['result_nao_selecionada'];
                    $obs_padrao = 'Recurso indeferido';
                break;
                
                case 'suplente':
                    $result = $this->config['result_suplente'];
                    $obs_padrao = 'Recurso: inscrição suplente';
                break;
                
                default:
                    die("O valor da coluna VALIDACAO da linha $i está incorreto ($eval). Os valores possíveis são 'selecionada' ou 'aprovada', 'invalida', 'nao selecionada' ou 'suplente'");
                
            }

            if (empty($obs)) {
                $obs = $obs_padrao;
            }

            $prop_raw = $slug . '_raw';
            $prop_filename = $slug . '_filename';
            
            $registration = $app->repo('Registration')->findOneBy(['number' => $num]);

            $count++;
            /* @TODO: implementar atualização de status?? */
            if (in_array($filename, $registration->{$prop_filename})) {
                $app->log->info("$name #{$count} {$registration->number} $eval - RECURSO JÁ PROCESSADO");
                continue;
            }
            
            $app->log->info("$name #{$count} {$registration} $eval");
     
            $user = $this->plugin->user;

            /* @TODO: versão para avaliação documental */
            
            $evaluation = $app->repo('RegistrationEvaluation')->findOneBy(['registration' => $registration, 'user' => $user]);

            if(!$evaluation) {
                $evaluation = new RegistrationEvaluation;
                $evaluation->user = $user;
                $evaluation->registration = $registration;
                $evaluation->evaluationData = ['status' => $result, "obs" => $obs];
            } else {
                $data = $evaluation->evaluationData;
                $data->status = $result;
                $data->obs .= "\n================\n\n{$obs}";

                $evaluation->evaluationData = $data;
            }
            
            $evaluation->__skipQueuingPCacheRecreation = true;
            $evaluation->result = $result;
            $evaluation->status = 1;
            $evaluation->save(true);


            $raws = $registration->{$prop_raw};
            $raws->$filename = $line;
            $registration->{$prop_raw} = $raws;

            $filenames = $registration->{$prop_filename};
            $filenames[] = $filename;
            $registration->{$prop_filename} = $filenames;

            $registration->save(true);
            $app->em->clear();
        }

        $app->enableAccessControl();

        // por causa do $app->em->clear(); não é possível mais utilizar a entidade para salvar
        $opportunity = $app->repo('Opportunity')->find($opportunity->id);

        $slug = $this->plugin->getSlug();

        $opportunity->refresh();
        $opportunity->name = $opportunity->name . ' ';
        $files = $opportunity->{$slug . '_processed_files'};
        $files->{basename($filename)} = date('d/m/Y \à\s H:i');
        $opportunity->{$slug . '_processed_files'} = $files;
        $opportunity->save(true);
        $this->finish('ok');
        
    }
}
