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

namespace App\Http\Controllers;

use App\DataMapper\Analytics\LivePreview;
use App\Factory\CreditFactory;
use App\Factory\InvoiceFactory;
use App\Factory\QuoteFactory;
use App\Factory\RecurringInvoiceFactory;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Preview\PreviewInvoiceRequest;
use App\Jobs\Util\PreviewPdf;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Repositories\CreditRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\RecurringInvoiceRepository;
use App\Services\PdfMaker\Design as PdfDesignModel;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\Design;
use App\Services\PdfMaker\PdfMaker;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\HtmlEngine;
use App\Utils\Ninja;
use App\Utils\PhantomJS\Phantom;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesInvoiceHtml;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Response;
use Turbo124\Beacon\Facades\LightLogs;

class PreviewController extends BaseController
{
    use MakesHash;
    use MakesInvoiceHtml;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Returns a template filled with entity variables.
     *
     * @return \Illuminate\Http\Response
     *
     * @OA\Post(
     *      path="/api/v1/preview",
     *      operationId="getPreview",
     *      tags={"preview"},
     *      summary="Returns a pdf preview",
     *      description="Returns a pdf preview.",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Response(
     *          response=200,
     *          description="The pdf response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),

     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function show()
    {
        if (request()->has('entity') &&
            request()->has('entity_id') &&
            ! empty(request()->input('entity')) &&
            ! empty(request()->input('entity_id')) &&
            request()->has('body')) {
            $design_object = json_decode(json_encode(request()->input('design')));

            if (! is_object($design_object)) {
                return response()->json(['message' => ctrans('texts.invalid_design_object')], 400);
            }

            $entity = ucfirst(request()->input('entity'));

            $class = "App\Models\\$entity";

            $pdf_class = "App\Jobs\\$entity\\Create{$entity}Pdf";

            $entity_obj = $class::whereId($this->decodePrimaryKey(request()->input('entity_id')))->company()->first();

            if (! $entity_obj) {
                return $this->blankEntity();
            }

            $entity_obj->load('client');

            App::forgetInstance('translator');
            $t = app('translator');
            App::setLocale($entity_obj->client->primary_contact()->preferredLocale());
            $t->replace(Ninja::transformTranslations($entity_obj->client->getMergedSettings()));

            $html = new HtmlEngine($entity_obj->invitations()->first());

            $design_namespace = 'App\Services\PdfMaker\Designs\\'.request()->design['name'];

            $design_class = new $design_namespace();

            $state = [
                'template' => $design_class->elements([
                    'client' => $entity_obj->client,
                    'entity' => $entity_obj,
                    'pdf_variables' => (array) $entity_obj->company->settings->pdf_variables,
                    'products' => request()->design['design']['product'],
                ]),
                'variables' => $html->generateLabelsAndValues(),
            ];

            $design = new Design(request()->design['name']);
            $maker = new PdfMaker($state);

            $maker
                ->design($design)
                ->build();

            if (request()->query('html') == 'true') {
                return $maker->getCompiledHTML;
            }

            //if phantom js...... inject here..
            if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
                return (new Phantom)->convertHtmlToPdf($maker->getCompiledHTML(true));
            }
            
            if(config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja'){
                return (new NinjaPdf())->build($maker->getCompiledHTML(true));
            }

            //else
            $file_path = PreviewPdf::dispatchNow($maker->getCompiledHTML(true), auth()->user()->company());

            return response()->download($file_path, basename($file_path), ['Cache-Control:' => 'no-cache'])->deleteFileAfterSend(true);
        }

        return $this->blankEntity();
    }

    public function live(PreviewInvoiceRequest $request)
    {
        $company = auth()->user()->company();

        MultiDB::setDb($company->db);

        info("preview db = ".$company->db);

        if($request->input('entity') == 'invoice'){
            $repo = new InvoiceRepository();
            $factory = InvoiceFactory::create($company->id, auth()->user()->id);
            $class = Invoice::class;
        }
        elseif($request->input('entity') == 'quote'){
            $repo = new QuoteRepository();
            $factory = QuoteFactory::create($company->id, auth()->user()->id);
            $class = Quote::class;

        }
        elseif($request->input('entity') == 'credit'){
            $repo = new CreditRepository();
            $factory = CreditFactory::create($company->id, auth()->user()->id);
            $class = Credit::class;
        }
        elseif($request->input('entity') == 'recurring_invoice'){
            $repo = new RecurringInvoiceRepository();
            $factory = RecurringInvoiceFactory::create($company->id, auth()->user()->id);
            $class = RecurringInvoice::class;
        }
            

        try {

            DB::connection($company->db)->beginTransaction();

            if($request->has('entity_id')){

                info("trying to find entity id = " . $this->decodePrimaryKey($request->input('entity_id')));
                info("company id = " . $company->id);

                $entity_obj = $class::on($company->db)
                                    ->where('id', $this->decodePrimaryKey($request->input('entity_id')))
                                    ->where('company_id', $company->id)
                                    ->withTrashed()
                                    ->first();

                if($entity_obj)
                    info("found a valid entity object");

                $entity_obj = $repo->save($request->all(), $entity_obj);

            }
            else {
                $entity_obj = $repo->save($request->all(), $factory);
            }

            $entity_obj->load('client');

            App::forgetInstance('translator');
            $t = app('translator');
            App::setLocale($entity_obj->client->contacts()->first()->preferredLocale());
            $t->replace(Ninja::transformTranslations($entity_obj->client->getMergedSettings()));

            $html = new HtmlEngine($entity_obj->invitations()->first());

            $design = \App\Models\Design::find($entity_obj->design_id);

            /* Catch all in case migration doesn't pass back a valid design */
            if(!$design)
                $design = \App\Models\Design::find(2);

            if ($design->is_custom) {
                $options = [
                'custom_partials' => json_decode(json_encode($design->design), true)
              ];
                $template = new PdfMakerDesign(PdfDesignModel::CUSTOM, $options);
            } else {
                $template = new PdfMakerDesign(strtolower($design->name));
            }

            $variables = $html->generateLabelsAndValues();

            $state = [
                'template' => $template->elements([
                    'client' => $entity_obj->client,
                    'entity' => $entity_obj,
                    'pdf_variables' => (array) $entity_obj->company->settings->pdf_variables,
                    '$product' => $design->design->product,
                    'variables' => $variables,
                ]),
                'variables' => $variables,
                'options' => [
                    'all_pages_header' => $entity_obj->client->getSetting('all_pages_header'),
                    'all_pages_footer' => $entity_obj->client->getSetting('all_pages_footer'),
                ],
            ];


            $maker = new PdfMaker($state);

            $maker
                ->design($template)
                ->build();

            DB::connection($company->db)->rollBack();

            if (request()->query('html') == 'true') {
                return $maker->getCompiledHTML;
            }


        }
        catch(\Exception $e){

            DB::connection($company->db)->rollBack();
            return;
        }


            //if phantom js...... inject here..
            if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
                return (new Phantom)->convertHtmlToPdf($maker->getCompiledHTML(true));
            }
            
            if(config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja'){
                return (new NinjaPdf())->build($maker->getCompiledHTML(true));
            }

            $file_path = PreviewPdf::dispatchNow($maker->getCompiledHTML(true), $company);


            if(Ninja::isHosted())
            {
                LightLogs::create(new LivePreview())
                         ->increment()
                         ->batch();
            }


        $response = Response::make($file_path, 200);
        $response->header('Content-Type', 'application/pdf');

        return $response;

    }


    private function blankEntity()
    {
        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations(auth()->user()->company()->settings));

        DB::connection(auth()->user()->company()->db)->beginTransaction();

        $client = Client::factory()->create([
                'user_id' => auth()->user()->id,
                'company_id' => auth()->user()->company()->id,
            ]);

        $contact = ClientContact::factory()->create([
                'user_id' => auth()->user()->id,
                'company_id' => auth()->user()->company()->id,
                'client_id' => $client->id,
                'is_primary' => 1,
                'send_email' => true,
            ]);

        $invoice = Invoice::factory()->create([
                    'user_id' => auth()->user()->id,
                    'company_id' => auth()->user()->company()->id,
                    'client_id' => $client->id,
                ]);

        $invitation = InvoiceInvitation::factory()->create([
                    'user_id' => auth()->user()->id,
                    'company_id' => auth()->user()->company()->id,
                    'invoice_id' => $invoice->id,
                    'client_contact_id' => $contact->id,
        ]);

        $invoice->setRelation('invitations', $invitation);
        $invoice->setRelation('client', $client);
        $invoice->setRelation('company', auth()->user()->company());
        $invoice->load('client');

        // nlog(print_r($invoice->toArray(),1));

        $design_object = json_decode(json_encode(request()->input('design')));

        if (! is_object($design_object)) {
            return response()->json(['message' => 'Invalid custom design object'], 400);
        }

        $html = new HtmlEngine($invoice->invitations()->first());

        $design = new Design(Design::CUSTOM, ['custom_partials' => request()->design['design']]);

        $state = [
            'template' => $design->elements([
                'client' => $invoice->client,
                'entity' => $invoice,
                'pdf_variables' => (array) $invoice->company->settings->pdf_variables,
                'products' => request()->design['design']['product'],
            ]),
            'variables' => $html->generateLabelsAndValues(),
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design($design)
            ->build();

        if (request()->query('html') == 'true') {
            return $maker->getCompiledHTML();
        }

        if (config('ninja.phantomjs_pdf_generation')) {
            return (new Phantom)->convertHtmlToPdf($maker->getCompiledHTML(true));
        }

        if(config('ninja.invoiceninja_hosted_pdf_generation')){
            return (new NinjaPdf())->build($maker->getCompiledHTML(true));
        }
            
        $file_path = PreviewPdf::dispatchNow($maker->getCompiledHTML(true), auth()->user()->company());

        DB::connection(auth()->user()->company()->db)->rollBack();

        $response = Response::make($file_path, 200);
        $response->header('Content-Type', 'application/pdf');

        return $response;
    }
}
