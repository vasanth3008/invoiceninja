<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\ViewComposers\PortalComposer;
use App\Models\RecurringInvoice;
use Illuminate\Support\Facades\Redirect;

class ContactHashLoginController extends Controller
{
    /**
     * Logs a user into the client portal using their contact_key
     * @param  string $contact_key  The contact key
     * @return Redirect
     */
    public function login(string $contact_key)
    {
        if (request()->has('subscription') && request()->subscription == 'true') {

            /** @var \App\Models\ClientContact $client_contact **/
            $client_contact = auth()->guard('contact');

            /** @var \App\Models\RecurringInvoice $recurring_invoice **/
            $recurring_invoice = RecurringInvoice::where('client_id', $client_contact->client->id)
                                                 ->whereNotNull('subscription_id')
                                                 ->whereNull('deleted_at')
                                                 ->first();

            return redirect()->route('client.recurring_invoice.show', $recurring_invoice->hashed_id);
        }

        return redirect($this->setRedirectPath());
    }

    public function magicLink(string $magic_link)
    {
        return redirect($this->setRedirectPath());
    }

    /**
     * Generic error page for client portal.
     *
     * @return void
     */
    public function errorPage()
    {
        return render('generic.error', [
            'title' => session()->get('title'),
            'notification' => session()->get('notification'),
            'account' => auth()->guard('contact')?->user()?->user?->account,
            'company' => auth()->guard('contact')?->user()?->user?->company
        ]);
    }

    private function setRedirectPath()
    {
        if (auth()->guard('contact')->user()->client->getSetting('enable_client_portal_dashboard') === true) {
            return '/client/dashboard';                                                                                              
        } elseif (auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_INVOICES) {
            return '/client/invoices';
        } elseif (auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_RECURRING_INVOICES) {
            return '/client/recurring_invoices';
        } elseif (auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_QUOTES) {
            return '/client/quotes';
        } elseif (auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_CREDITS) {
            return '/client/credits';
        } elseif (auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_TASKS) {
            return '/client/tasks';
        } elseif (auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_EXPENSES) {
            return '/client/expenses';
        }
    }
}
