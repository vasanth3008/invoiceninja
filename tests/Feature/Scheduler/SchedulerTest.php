<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Scheduler;

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\Client;
use App\Models\Scheduler;
use Tests\MockAccountData;
use App\Utils\Traits\MakesHash;
use App\Models\RecurringInvoice;
use App\Factory\SchedulerFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use App\DataMapper\Schedule\ClientStatement;
use App\Services\Scheduler\SchedulerService;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\WithoutEvents;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * @test
 * @covers  App\Services\Scheduler\SchedulerService
 */
class SchedulerTest extends TestCase
{
    use MakesHash;
    use MockAccountData;
    use WithoutEvents;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testClientCountResolution()
    {
        $c = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'number' => rand(1000, 100000),
            'name' => 'A fancy client'
        ]);

        $c2 = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'number' => rand(1000, 100000),
            'name' => 'A fancy client'
        ]);

        $data = [
            'name' => 'A test statement scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'client_statement',
            'parameters' => [
                'date_range' => ClientStatement::LAST_MONTH,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [
                    $c2->hashed_id,
                    $c->hashed_id
                ],
            ],
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/task_schedulers', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(200);

        $data = $response->json();

        $scheduler = Scheduler::find($this->decodePrimaryKey($data['data']['id']));

        $this->assertInstanceOf(Scheduler::class, $scheduler);

        $this->assertCount(2, $scheduler->parameters['clients']);

        $q = Client::query()
              ->where('company_id', $scheduler->company_id)
              ->whereIn('id', $this->transformKeys($scheduler->parameters['clients']))
              ->cursor();

        $this->assertCount(2, $q);
    }

    public function testClientsValidationInScheduledTask()
    {
        $c = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'number' => rand(1000, 100000),
            'name' => 'A fancy client'
        ]);

        $c2 = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'number' => rand(1000, 100000),
            'name' => 'A fancy client'
        ]);

        $data = [
            'name' => 'A test statement scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'client_statement',
            'parameters' => [
                'date_range' => ClientStatement::LAST_MONTH,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [
                    $c2->hashed_id,
                    $c->hashed_id
                ],
            ],
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/task_schedulers', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(200);


        $data = [
            'name' => 'A single Client',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->addDay()->format('Y-m-d'),
            'template' => 'client_statement',
            'parameters' => [
                'date_range' => ClientStatement::LAST_MONTH,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [
                    $c2->hashed_id,
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);
        

        $data = [
            'name' => 'An invalid Client',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'client_statement',
            'parameters' => [
                'date_range' => ClientStatement::LAST_MONTH,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [
                    'xx33434',
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(422);
    }


    public function testCalculateNextRun()
    {
        $scheduler = SchedulerFactory::create($this->company->id, $this->user->id);
        
        $data = [
            'name' => 'A test statement scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'client_statement',
            'parameters' => [
                'date_range' => ClientStatement::LAST_MONTH,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [],
            ],
        ];

        $scheduler->fill($data);
        $scheduler->save();

        $service_object = new SchedulerService($scheduler);

        $reflectionMethod = new \ReflectionMethod(SchedulerService::class, 'calculateNextRun');
        $reflectionMethod->setAccessible(true);
        $method = $reflectionMethod->invoke(new SchedulerService($scheduler));

        $scheduler->fresh();
        $offset = $this->company->timezone_offset();

        $this->assertEquals(now()->startOfDay()->addMonthNoOverflow()->addSeconds($offset)->format('Y-m-d'), $scheduler->next_run->format('Y-m-d'));
    }

    public function testCalculateStartAndEndDates()
    {
        $this->travelTo(Carbon::parse('2023-01-01'));

        $scheduler = SchedulerFactory::create($this->company->id, $this->user->id);
        
        $data = [
            'name' => 'A test statement scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => "2023-01-01",
            'template' => 'client_statement',
            'parameters' => [
                'date_range' => ClientStatement::LAST_MONTH,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [],
            ],
        ];

        $scheduler->fill($data);
        $scheduler->save();

        $service_object = new SchedulerService($scheduler);

        $reflectionMethod = new \ReflectionMethod(SchedulerService::class, 'calculateStartAndEndDates');
        $reflectionMethod->setAccessible(true);
        $method = $reflectionMethod->invoke(new SchedulerService($scheduler));

        $this->assertIsArray($method);

        $this->assertEquals(ClientStatement::LAST_MONTH, $scheduler->parameters['date_range']);

        $this->assertEqualsCanonicalizing(['2022-12-01','2022-12-31'], $method);
    }

    public function testCalculateStatementProperties()
    {
        $scheduler = SchedulerFactory::create($this->company->id, $this->user->id);
        
        $data = [
            'name' => 'A test statement scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'client_statement',
            'parameters' => [
                'date_range' => ClientStatement::LAST_MONTH,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [],
            ],
        ];

        $scheduler->fill($data);
        $scheduler->save();

        $service_object = new SchedulerService($scheduler);

        // $reflection = new \ReflectionClass(get_class($service_object));
        // $method = $reflection->getMethod('calculateStatementProperties');
        // $method->setAccessible(true);
        // $method->invokeArgs($service_object, []);

        $reflectionMethod = new \ReflectionMethod(SchedulerService::class, 'calculateStatementProperties');
        $reflectionMethod->setAccessible(true);
        $method = $reflectionMethod->invoke(new SchedulerService($scheduler)); // 'baz'

        $this->assertIsArray($method);

        $this->assertEquals('paid', $method['status']);
    }

    public function testGetThisMonthRange()
    {
        $this->travelTo(Carbon::parse('2023-01-14'));

        $this->assertEqualsCanonicalizing(['2023-01-01','2023-01-31'], $this->getDateRange(ClientStatement::THIS_MONTH));
        $this->assertEqualsCanonicalizing(['2023-01-01','2023-03-31'], $this->getDateRange(ClientStatement::THIS_QUARTER));
        $this->assertEqualsCanonicalizing(['2023-01-01','2023-12-31'], $this->getDateRange(ClientStatement::THIS_YEAR));

        $this->assertEqualsCanonicalizing(['2022-12-01','2022-12-31'], $this->getDateRange(ClientStatement::LAST_MONTH));
        $this->assertEqualsCanonicalizing(['2022-10-01','2022-12-31'], $this->getDateRange(ClientStatement::LAST_QUARTER));
        $this->assertEqualsCanonicalizing(['2022-01-01','2022-12-31'], $this->getDateRange(ClientStatement::LAST_YEAR));

        $this->travelBack();
    }

    private function getDateRange($range)
    {
        return match ($range) {
            ClientStatement::LAST7 => [now()->startOfDay()->subDays(7)->format('Y-m-d'), now()->startOfDay()->format('Y-m-d')],
            ClientStatement::LAST30 => [now()->startOfDay()->subDays(30)->format('Y-m-d'), now()->startOfDay()->format('Y-m-d')],
            ClientStatement::LAST365 => [now()->startOfDay()->subDays(365)->format('Y-m-d'), now()->startOfDay()->format('Y-m-d')],
            ClientStatement::THIS_MONTH => [now()->startOfDay()->firstOfMonth()->format('Y-m-d'), now()->startOfDay()->lastOfMonth()->format('Y-m-d')],
            ClientStatement::LAST_MONTH => [now()->startOfDay()->subMonthNoOverflow()->firstOfMonth()->format('Y-m-d'), now()->startOfDay()->subMonthNoOverflow()->lastOfMonth()->format('Y-m-d')],
            ClientStatement::THIS_QUARTER => [now()->startOfDay()->firstOfQuarter()->format('Y-m-d'), now()->startOfDay()->lastOfQuarter()->format('Y-m-d')],
            ClientStatement::LAST_QUARTER => [now()->startOfDay()->subQuarterNoOverflow()->firstOfQuarter()->format('Y-m-d'), now()->startOfDay()->subQuarterNoOverflow()->lastOfQuarter()->format('Y-m-d')],
            ClientStatement::THIS_YEAR => [now()->startOfDay()->firstOfYear()->format('Y-m-d'), now()->startOfDay()->lastOfYear()->format('Y-m-d')],
            ClientStatement::LAST_YEAR => [now()->startOfDay()->subYearNoOverflow()->firstOfYear()->format('Y-m-d'), now()->startOfDay()->subYearNoOverflow()->lastOfYear()->format('Y-m-d')],
            ClientStatement::CUSTOM_RANGE => [$this->scheduler->parameters['start_date'], $this->scheduler->parameters['end_date']],
            default => [now()->startOfDay()->firstOfMonth()->format('Y-m-d'), now()->startOfDay()->lastOfMonth()->format('Y-m-d')],
        };
    }

    public function testClientStatementGeneration()
    {
        $data = [
            'name' => 'A test statement scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'client_statement',
            'parameters' => [
                'date_range' => ClientStatement::LAST_MONTH,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);
    }

    public function testDeleteSchedule()
    {
        $data = [
            'ids' => [$this->scheduler->hashed_id],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers/bulk?action=delete', $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$this->scheduler->hashed_id],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers/bulk?action=restore', $data)
        ->assertStatus(200);
    }

    public function testRestoreSchedule()
    {
        $data = [
            'ids' => [$this->scheduler->hashed_id],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers/bulk?action=archive', $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$this->scheduler->hashed_id],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers/bulk?action=restore', $data)
        ->assertStatus(200);
    }

    public function testArchiveSchedule()
    {
        $data = [
            'ids' => [$this->scheduler->hashed_id],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers/bulk?action=archive', $data)
        ->assertStatus(200);
    }

    public function testSchedulerPost()
    {
        $data = [
            'name' => 'A different Name',
            'frequency_id' => 5,
            'next_run' => now()->addDays(2)->format('Y-m-d'),
            'template' =>'client_statement',
            'parameters' => [],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);
    }

    public function testSchedulerPut()
    {
        $data = [
            'name' => 'A different Name',
            'frequency_id' => 5,
            'next_run' => now()->addDays(2)->format('Y-m-d'),
            'template' =>'client_statement',
            'parameters' => [],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/task_schedulers/'.$this->scheduler->hashed_id, $data);

        $response->assertStatus(200);
    }

    public function testSchedulerGet()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/task_schedulers');

        $response->assertStatus(200);
    }

    public function testSchedulerCreate()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/task_schedulers/create');

        $response->assertStatus(200);
    }


    // public function testSchedulerPut()
    // {
    //     $data = [
    //         'description' => $this->faker->firstName(),
    //     ];

    //     $response = $this->withHeaders([
    //         'X-API-SECRET' => config('ninja.api_secret'),
    //         'X-API-TOKEN' => $this->token,
    //     ])->put('/api/v1/task_schedulers/'.$this->encodePrimaryKey($this->task->id), $data);

    //     $response->assertStatus(200);
    // }



    // public function testSchedulerCantBeCreatedWithWrongData()
    // {
    //     $data = [
    //         'repeat_every' => Scheduler::DAILY,
    //         'job' => Scheduler::CREATE_CLIENT_REPORT,
    //         'date_key' => '123',
    //         'report_keys' => ['test'],
    //         'date_range' => 'all',
    //         // 'start_from' => '2022-01-01'
    //     ];

    //     $response = false;

    //     $response = $this->withHeaders([
    //         'X-API-SECRET' => config('ninja.api_secret'),
    //         'X-API-TOKEN' => $this->token,
    //     ])->post('/api/v1/task_scheduler/', $data);

    //     $response->assertSessionHasErrors();
    // }

    // public function testSchedulerCanBeUpdated()
    // {
    //     $response = $this->createScheduler();

    //     $arr = $response->json();
    //     $id = $arr['data']['id'];

    //     $scheduler = Scheduler::find($this->decodePrimaryKey($id));

    //     $updateData = [
    //         'start_from' => 1655934741,
    //     ];
    //     $response = $this->withHeaders([
    //         'X-API-SECRET' => config('ninja.api_secret'),
    //         'X-API-TOKEN' => $this->token,
    //     ])->put('/api/v1/task_scheduler/'.$this->encodePrimaryKey($scheduler->id), $updateData);

    //     $responseData = $response->json();
    //     $this->assertEquals($updateData['start_from'], $responseData['data']['start_from']);
    // }

    // public function testSchedulerCanBeSeen()
    // {
    //     $response = $this->createScheduler();

    //     $arr = $response->json();
    //     $id = $arr['data']['id'];

    //     $scheduler = Scheduler::find($this->decodePrimaryKey($id));

    //     $response = $this->withHeaders([
    //         'X-API-SECRET' => config('ninja.api_secret'),
    //         'X-API-TOKEN' => $this->token,
    //     ])->get('/api/v1/task_scheduler/'.$this->encodePrimaryKey($scheduler->id));

    //     $arr = $response->json();
    //     $this->assertEquals('create_client_report', $arr['data']['action_name']);
    // }

    // public function testSchedulerJobCanBeUpdated()
    // {
    //     $response = $this->createScheduler();

    //     $arr = $response->json();
    //     $id = $arr['data']['id'];

    //     $scheduler = Scheduler::find($this->decodePrimaryKey($id));

    //     $this->assertSame('create_client_report', $scheduler->action_name);

    //     $updateData = [
    //         'job' => Scheduler::CREATE_CREDIT_REPORT,
    //         'date_range' => 'all',
    //         'report_keys' => ['test1'],
    //     ];

    //     $response = $this->withHeaders([
    //         'X-API-SECRET' => config('ninja.api_secret'),
    //         'X-API-TOKEN' => $this->token,
    //     ])->put('/api/v1/task_scheduler/'.$this->encodePrimaryKey($scheduler->id), $updateData);

    //     $updatedSchedulerJob = Scheduler::first()->action_name;
    //     $arr = $response->json();

    //     $this->assertSame('create_credit_report', $arr['data']['action_name']);
    // }

    // public function createScheduler()
    // {
    //     $data = [
    //         'repeat_every' => Scheduler::DAILY,
    //         'job' => Scheduler::CREATE_CLIENT_REPORT,
    //         'date_key' => '123',
    //         'report_keys' => ['test'],
    //         'date_range' => 'all',
    //         'start_from' => '2022-01-01',
    //     ];

    //     return $response = $this->withHeaders([
    //         'X-API-SECRET' => config('ninja.api_secret'),
    //         'X-API-TOKEN' => $this->token,
    //     ])->post('/api/v1/task_scheduler/', $data);
    // }
}
