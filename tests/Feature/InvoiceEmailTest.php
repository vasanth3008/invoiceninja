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

use Tests\TestCase;
use Tests\MockAccountData;
use App\Jobs\Entity\EmailEntity;
use Illuminate\Support\Facades\Event;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * @test
 * @covers App\Jobs\Invoice\EmailInvoice
 */
class InvoiceEmailTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    use GeneratesCounter;

    protected function setUp() :void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();

        // $this->withoutExceptionHandling();

    }

    public function testTemplateValidation()
    {
        $data = [
            "body" => "hey what's up", 
            "entity" => 'invoice', 
            "entity_id"=> $this->invoice->hashed_id, 
            "subject"=> 'Reminder $number', 
            "template"=> "first_custom"
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/emails', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(422);

    }

    public function test_cc_email_implementation()
    {
        $data = [
            'template' => 'email_template_invoice',
            'entity' => 'invoice',
            'entity_id' => $this->invoice->hashed_id,
            'cc_email' => 'jj@gmail.com'
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/emails', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(200);

    }

    public function test_initial_email_send_emails()
    {
        $this->invoice->date = now();
        $this->invoice->due_date = now()->addDays(7);
        $this->invoice->number = $this->getNextInvoiceNumber($this->client, $this->invoice);

        $this->invoice->client_id = $this->client->id;

        $client_settings = $this->client->settings;
        $client_settings->email_style = 'dark';
        $this->client->settings = $client_settings;
        $this->client->save();

        $this->invoice->setRelation('client', $this->client);

        $this->invoice->save();

        $this->invoice->invitations->each(function ($invitation) {
            if ($invitation->contact->send_email && $invitation->contact->email) {
                EmailEntity::dispatch($invitation, $invitation->company);

                Event::fake();
                Event::assertDispatched(EmailEntity::class);
            }
        });

        $this->assertTrue(true);
    }

    public function testTemplateThemes()
    {
        $settings = $this->company->settings;
        $settings->email_style = 'light';

        $this->company->settings = $settings;
        $this->company->save();

        $this->invoice->date = now();
        $this->invoice->due_date = now()->addDays(7);
        $this->invoice->number = $this->getNextInvoiceNumber($this->client, $this->invoice);

        $this->invoice->client_id = $this->client->id;
        $this->invoice->setRelation('client', $this->client);

        $this->invoice->save();

        $this->invoice->invitations->each(function ($invitation) {
            if ($invitation->contact->send_email && $invitation->contact->email) {
                EmailEntity::dispatch($invitation, $invitation->company);

                
Event::fake();
Event::assertDispatched(EmailEntity::class);

            }
        });

        $settings = $this->company->settings;
        $settings->email_style = 'dark';

        $this->company->settings = $settings;
        $this->company->save();

        $this->invoice->date = now();
        $this->invoice->due_date = now()->addDays(7);
        $this->invoice->number = $this->getNextInvoiceNumber($this->client, $this->invoice);

        $this->invoice->client_id = $this->client->id;

        $client_settings = $this->client->settings;
        $client_settings->email_style = 'dark';
        $this->client->settings = $client_settings;
        $this->client->save();

        $this->invoice->setRelation('client', $this->client);
        $this->invoice->save();

        $this->invoice->invitations->each(function ($invitation) {
            if ($invitation->contact->send_email && $invitation->contact->email) {
                EmailEntity::dispatch($invitation, $invitation->company);

                
Event::fake();
Event::assertDispatched(EmailEntity::class);

            }
        });

        $settings = $this->company->settings;
        $settings->email_style = 'plain';

        $this->company->settings = $settings;
        $this->company->save();

        $this->invoice->date = now();
        $this->invoice->due_date = now()->addDays(7);
        $this->invoice->number = $this->getNextInvoiceNumber($this->client, $this->invoice);

        $this->invoice->client_id = $this->client->id;
        $this->invoice->setRelation('client', $this->client);

        $this->invoice->save();

        $this->invoice->invitations->each(function ($invitation) {
            if ($invitation->contact->send_email && $invitation->contact->email) {
                EmailEntity::dispatch($invitation, $invitation->company);

                
Event::fake();
Event::assertDispatched(EmailEntity::class);

            }
        });

        $this->assertTrue(true);
    }
}
