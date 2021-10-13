<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace Tests\Browser\ClientPortal\Gateways\Stripe;

use App\DataMapper\FeesAndLimits;
use App\Models\Client;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\ClientPortal\Login;
use Tests\DuskTestCase;

class SEPATest extends DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (static::$browsers as $browser) {
            $browser->driver->manage()->deleteAllCookies();
        }

        $this->browse(function (Browser $browser) {
            $browser
                ->visit(new Login())
                ->auth();
        });

        $this->disableCompanyGateways();

        // Enable Stripe.
        CompanyGateway::where('gateway_key', 'd14dd26a37cecc30fdd65700bfb55b23')->restore();

        // Enable SEPA.
        $cg = CompanyGateway::where('gateway_key', 'd14dd26a37cecc30fdd65700bfb55b23')->firstOrFail();
        $fees_and_limits = $cg->fees_and_limits;
        $fees_and_limits->{GatewayType::SEPA} = new FeesAndLimits();
        $cg->fees_and_limits = $fees_and_limits;
        $cg->save();

        // SEPA required DE to be billing country.
        $client = Client::first();
        $client->country_id = 276;
        $client->save();
    }
}
