<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use App\Models\Task;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\TaskController
 */
class TaskApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }

    private function checkTimeLog($log)
    {

        $result = array_column($log, 0);

        asort($result);

        $new_array = [];

        foreach($result as $key => $value)
            $new_array[] = $log[$key];

        foreach($new_array as $key => $array)
        {
            $next = false;

            if(count($new_array) >1 && $array[1] == 0)
                return false;

            /* First test is to check if the start time is greater than the end time */ 
            /* Ignore the last value for now, we'll do a separate check for this */ 
            if($array[0] > $array[1] && $array[1] != 0){
                return false;
            }

            if(array_key_exists($key+1, $new_array)){
                $next = $new_array[$key+1];
            }

            /* check the next time log and ensure the start time is GREATER than the end time of the previous record */
            if($next && $next[0] < $array[1]){
                return false;
            }

            $last_row = end($new_array);

            if($last_row[1] != 0 && $last_row[0] > $last_row[1]){
                nlog($last_row[0]. " ".$last_row[1]);
                return false;
            }

            return true;
        }

    }

    public function testTimeLogChecker1()
    {

        $log = [
            [50,0]
        ];

        $this->assertTrue($this->checkTimeLog($log));

    }

    public function testTimeLogChecker2()
    {

        $log = [
            [4,5],
            [5,1]
        ];


        $this->assertFalse($this->checkTimeLog($log));

    }


    public function testTimeLogChecker3()
    {

        $log = [
            [4,5],
            [3,50]
        ];


        $this->assertFalse($this->checkTimeLog($log));

    }


    public function testTimeLogChecker4()
    {

        $log = [
            [4,5],
            [3,0]
        ];


        $this->assertFalse($this->checkTimeLog($log));

    }

    public function testTimeLogChecker5()
    {

        $log = [
            [4,5],
            [3,1]
        ];


        $this->assertFalse($this->checkTimeLog($log));

    }

    public function testTimeLogChecker6()
    {

        $log = [
            [4,5],
            [1,3],
        ];


        $this->assertTrue($this->checkTimeLog($log));

    }

    public function testTimeLogChecker7()
    {

        $log = [
            [1,3],
            [4,5]
        ];


        $this->assertTrue($this->checkTimeLog($log));

    }

    public function testTimeLogChecker8()
    {

        $log = [
            [1,3],
            [50,0]
        ];

        $this->assertTrue($this->checkTimeLog($log));

    }

    public function testTimeLogChecker9()
    {

        $log = [
            [4,5,'bb'],
            [50,0,'aa'],
        ];

        $this->assertTrue($this->checkTimeLog($log));

    }



    public function testTimeLogChecker10()
    {

        $log = [
            [4,5,'5'],
            [50,0,'3'],
        ];

        $this->assertTrue($this->checkTimeLog($log));

    }


    public function testTimeLogChecker11()
    {

        $log = [
            [1,2,'a'],
            [3,4,'d'],
        ];

        $this->assertTrue($this->checkTimeLog($log));

    }


    public function testTaskListClientStatus()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tasks?client_status=invoiced')
          ->assertStatus(200);

    }

    public function testTaskLockingGate()
    {
        $data = [
            'timelog' => [[1,2,'a'],[3,4,'d']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks', $data);

        $arr = $response->json();
        $response->assertStatus(200);
        
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/tasks/' . $arr['data']['id'], $data);

        $arr = $response->json();

        $response->assertStatus(200);

        $task = Task::find($this->decodePrimaryKey($arr['data']['id']));
        $task->invoice_id = $this->invoice->id;
        $task->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/tasks/' . $arr['data']['id'], $data);

        $arr = $response->json();

        $response->assertStatus(200);

        $task = Task::find($this->decodePrimaryKey($arr['data']['id']));
        $task->company->invoice_task_lock = true;
        $task->invoice_id = $this->invoice->id;
        $task->push();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/tasks/' . $arr['data']['id'], $data);

        $arr = $response->json();

        $response->assertStatus(401);

    }


    // public function testTaskLocking()
    // {
    //     $data = [
    //         'timelog' => [[1,2],[3,4]],
    //     ];

    //     $response = $this->withHeaders([
    //         'X-API-SECRET' => config('ninja.api_secret'),
    //         'X-API-TOKEN' => $this->token,
    //     ])->post('/api/v1/tasks', $data);

    //     $arr = $response->json();
    //     $response->assertStatus(200);
        

    //     $response = $this->withHeaders([
    //         'X-API-SECRET' => config('ninja.api_secret'),
    //         'X-API-TOKEN' => $this->token,
    //     ])->putJson('/api/v1/tasks/' . $arr['data']['id'], $data);

    //     $arr = $response->json();

    //     $response->assertStatus(200);

    // }




    public function testTimeLogValidation()
    {
        $data = [
            'timelog' => $this->faker->firstName(),
        ];

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/tasks', $data);

            $arr = $response->json();
        } catch (ValidationException $e) {
            $response->assertStatus(302);
        }

    }

    public function testTimeLogValidation1()
    {
        $data = [
            'timelog' => [[1,2],[3,4]],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks', $data);

        $arr = $response->json();
        $response->assertStatus(200);
        
    }



    public function testTimeLogValidation2()
    {
        $data = [
            'timelog' => [],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks', $data);

        $arr = $response->json();
        $response->assertStatus(200);
        

    }

    public function testTimeLogValidation3()
    {
        $data = [
            'timelog' => [["a","b",'d'],["c","d",'d']],
        ];

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/tasks', $data);

            $arr = $response->json();
        } catch (ValidationException $e) {
            $response->assertStatus(302);
        }

    }

    public function testTimeLogValidation4()
    {
        $data = [
            'timelog' => [[1,2,'d'],[3,0,'d']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks', $data);

        $arr = $response->json();
        $response->assertStatus(200);
        

    }



    public function testStartTask()
    {
        $log = [
            [2, 1,'d'],
            [10, 20,'d'],
        ];

        $last = end($log);

        $this->assertEquals(10, $last[0]);
        $this->assertEquals(20, $last[1]);

        $new = [time(), 0];

        array_push($log, $new);

        $this->assertEquals(3, count($log));

        //test task is started
        $last = end($log);
        $this->assertTrue($last[1] === 0);

        //stop task
        $last = end($log);
        $last[1] = time();

        $this->assertTrue($last[1] !== 0);
    }

    public function testTaskPost()
    {
        $data = [
            'description' => $this->faker->firstName(),
            'number' => 'taskynumber',
            'client_id' => $this->client->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertEquals('taskynumber', $arr['data']['number']);
        $this->assertLessThan(5, strlen($arr['data']['time_log']));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/tasks/'.$arr['data']['id'], $data);

        $response->assertStatus(200);

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/tasks', $data);

            $arr = $response->json();
        } catch (ValidationException $e) {
            $response->assertStatus(302);
        }

        $this->assertNotEmpty($arr['data']['number']);
    }

    public function testTaskPostNoDefinedTaskNumber()
    {
        $data = [
            'description' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks', $data);

        $arr = $response->json();
        $response->assertStatus(200);
        $this->assertNotEmpty($arr['data']['number']);
    }

    public function testTaskWithBadClientId()
    {
        $data = [
            'client_id' => $this->faker->firstName(),
        ];

        try {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks', $data);
            $arr = $response->json();
        } catch (ValidationException $e) {
            $response->assertStatus(302);
        }

    }

    public function testTaskPostWithActionStart()
    {
        $data = [
            'description' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks?action=start', $data);

        $arr = $response->json();
        $response->assertStatus(200);
    }

    public function testTaskPut()
    {
        $data = [
            'description' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id), $data);

        $response->assertStatus(200);
    }

    public function testTasksGet()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tasks');

        $response->assertStatus(200);
    }

    public function testTaskGet()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id));

        $response->assertStatus(200);
    }

    public function testTaskNotArchived()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id));

        $arr = $response->json();

        $this->assertEquals(0, $arr['data']['archived_at']);
    }

    public function testTaskArchived()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->task->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks/bulk?action=archive', $data);

        $arr = $response->json();

        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testTaskRestored()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->task->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks/bulk?action=restore', $data);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data'][0]['archived_at']);
    }

    public function testTaskDeleted()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->task->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks/bulk?action=delete', $data);

        $arr = $response->json();

        $this->assertTrue($arr['data'][0]['is_deleted']);
    }

    public function testTaskPostWithStartAction()
    {
        $data = [
            'description' => $this->faker->firstName(),
            'number' => 'taskynumber2',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks?start=true', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertEquals('taskynumber2', $arr['data']['number']);
        $this->assertGreaterThan(5, strlen($arr['data']['time_log']));
    }

    public function testTaskPostWithStopAction()
    {
        $data = [
            'description' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks?stop=true', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertLessThan(5, strlen($arr['data']['time_log']));
    }
}
