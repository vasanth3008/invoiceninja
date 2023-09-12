<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\CSV;

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\DateFormat;
use App\Models\Task;
use App\Models\Timezone;
use App\Transformers\TaskTransformer;
use App\Utils\Ninja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class TaskExport extends BaseExport
{

    private $entity_transformer;

    public string $date_key = 'created_at';

    private string $date_format = 'YYYY-MM-DD';

    public Writer $csv;

    private array $storage_array = [];

    private array $storage_item_array = [];

    public array $entity_keys = [
        'start_date' => 'start_date',
        'end_date' => 'end_date',
        'duration' => 'duration',
        'rate' => 'rate',
        'number' => 'number',
        'description' => 'description',
        'custom_value1' => 'custom_value1',
        'custom_value2' => 'custom_value2',
        'custom_value3' => 'custom_value3',
        'custom_value4' => 'custom_value4',
        'status' => 'status_id',
        'project' => 'project_id',
     ];

    private array $decorate_keys = [
        'status',
        'project',
        'client',
        'invoice',
        'start_date',
        'end_date',
        'duration',
    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->entity_transformer = new TaskTransformer();
    }

    public function init(): Builder
    {
        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $this->date_format = DateFormat::find($this->company->settings->date_format_id)->format;
        ksort($this->entity_keys);

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->task_report_keys);
        }

        $query = Task::query()
                        ->withTrashed()
                        ->where('company_id', $this->company->id)
                        ->where('is_deleted', 0);

        $query = $this->addDateRange($query);

        return $query;

    }

    public function run()
    {

        $query = $this->init();

        //load the CSV document from a string
        $this->csv = Writer::createFromString();

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query->cursor()
              ->each(function ($entity) {
                  $this->buildRow($entity);
              });

        $this->csv->insertAll($this->storage_array);

        return $this->csv->toString();
    }


    public function returnJson()
    {
        $query = $this->init();

        $headerdisplay = $this->buildHeader();

        $header = collect($this->input['report_keys'])->map(function ($key, $value) use($headerdisplay){
                return ['identifier' => $value, 'display_value' => $headerdisplay[$value]];
            })->toArray();

        $query->cursor()
                ->each(function ($resource) {
        
                    $this->buildRow($resource);
        
                    foreach($this->storage_array as $row)
                    {
                        $this->storage_item_array[] = $this->processMetaData($row, $resource);
                    }

                    $this->storage_array = [];
                });
        
        return array_merge(['columns' => $header], $this->storage_item_array);
    }

    private function buildRow(Task $task)
    {
        $entity = [];
        $transformed_entity = $this->entity_transformer->transform($task);

        foreach (array_values($this->input['report_keys']) as $key) {

            $parts = explode('.', $key);

            if (is_array($parts) && $parts[0] == 'task' && array_key_exists($parts[1], $transformed_entity)) {
                $entity[$key] = $transformed_entity[$parts[1]];
            } elseif (array_key_exists($key, $transformed_entity)) {
                $entity[$key] = $transformed_entity[$key];
            } else {
                $entity[$key] = $this->resolveKey($key, $task, $this->entity_transformer);
            }

            // $keyval = array_search($key, $this->entity_keys);

            // if(!$keyval) {
            //     $keyval = array_search(str_replace("task.", "", $key), $this->entity_keys) ?? $key;
            // }

            // if(!$keyval) {
            //     $keyval = $key;
            // }

            // if (array_key_exists($key, $transformed_entity)) {
            //     $entity[$keyval] = $transformed_entity[$key];
            // } elseif (array_key_exists($keyval, $transformed_entity)) {
            //     $entity[$keyval] = $transformed_entity[$keyval];
            // }
            // else {
            //     $entity[$keyval] = $this->resolveKey($keyval, $task, $this->entity_transformer);
            // }
        }

        $entity['start_date'] = '';
        $entity['end_date'] = '';
        $entity['duration'] = '';

        if (is_null($task->time_log) || (is_array(json_decode($task->time_log, 1)) && count(json_decode($task->time_log, 1)) == 0)) {
            $this->storage_array[] = $entity;
        } else {
            $this->iterateLogs($task, $entity);
        }
        
    }

    private function iterateLogs(Task $task, array $entity)
    {
        $timezone = Timezone::find($task->company->settings->timezone_id);
        $timezone_name = 'US/Eastern';

        if ($timezone) {
            $timezone_name = $timezone->name;
        }

        $logs = json_decode($task->time_log, 1);

        $date_format_default = 'Y-m-d';

        $date_format = DateFormat::find($task->company->settings->date_format_id);

        if ($date_format) {
            $date_format_default = $date_format->format;
        }

        foreach ($logs as $key => $item) {
            if (in_array('task.start_date', $this->input['report_keys']) || in_array('start_date', $this->input['report_keys'])) {
                $entity['task.start_date'] = Carbon::createFromTimeStamp($item[0])->setTimezone($timezone_name)->format($date_format_default);
            }

            if ((in_array('task.end_date', $this->input['report_keys']) || in_array('end_date', $this->input['report_keys'])) && $item[1] > 0) {
                $entity['task.end_date'] = Carbon::createFromTimeStamp($item[1])->setTimezone($timezone_name)->format($date_format_default);
            }

            if ((in_array('task.end_date', $this->input['report_keys']) || in_array('end_date', $this->input['report_keys'])) && $item[1] == 0) {
                $entity['task.end_date'] = ctrans('texts.is_running');
            }

            if (in_array('task.duration', $this->input['report_keys']) || in_array('duration', $this->input['report_keys'])) {
                $entity['task.duration'] = $task->calcDuration();
            }
            
            $entity = $this->decorateAdvancedFields($task, $entity);
            
            $this->storage_array[] = $entity;
            
            unset($entity['start_date']);
            unset($entity['end_date']);
            unset($entity['duration']);
        }

    }

    private function decorateAdvancedFields(Task $task, array $entity) :array
    {
        if (in_array('status_id', $this->input['report_keys'])) {
            $entity['status'] = $task->status()->exists() ? $task->status->name : '';
        }

        if (in_array('project_id', $this->input['report_keys'])) {
            $entity['project'] = $task->project()->exists() ? $task->project->name : '';
        }

        return $entity;
    }
}
