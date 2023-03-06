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

namespace App\Services\Email;

use App\Models\User;
use App\Models\Quote;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Vendor;
use App\Models\Invoice;
use App\Models\ClientContact;
use App\Models\PurchaseOrder;
use App\Models\VendorContact;
use App\Models\QuoteInvitation;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
use Illuminate\Mail\Mailables\Address;
use App\Models\PurchaseOrderInvitation;

/**
 * EmailObject.
 */
class EmailObject
{
    public array $to = [];

    public ?Address $from = null;

    public array $reply_to = [];

    public array $cc = [];

    public array $bcc = [];

    public ?string $subject = null;

    public ?string $body = null;

    public array $attachments = [];

    public string $company_key;

    public ?object $settings = null;

    public bool $whitelabel = false;

    public ?string $logo = null;

    public ?string $signature = null;

    public ?string $greeting = null;

    public ?int $invitation_id = null;

    public InvoiceInvitation | QuoteInvitation | CreditInvitation | PurchaseOrderInvitation | null $invitation;
    
    public ?int $entity_id = null;

    public Invoice | Quote | Credit | PurchaseOrder | null $entity;
    
    public ?int $client_id = null;

    public ?Client $client;
    
    public ?int $vendor_id = null;

    public ?Vendor $vendor;

    public ?int $user_id = null;

    public ?User $user;

    public ?int $client_contact_id = null;

    public ClientContact | VendorContact | null  $contact;

    public ?int $vendor_contact_id = null;

    public ?string $email_template_body = null;

    public ?string $email_template_subject = null;

    public ?string $html_template = null;

    public ?string $text_template = 'email.template.text';

    public array $headers = [];

    public ?string $entity_class = null;

    public array $variables = [];

    public bool $override = false;

    public ?string $invitation_key = null;
}
