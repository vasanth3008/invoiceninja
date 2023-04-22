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

namespace App\DataMapper\Tax;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\DataMapper\Tax\ZipTax\Response;

class BaseRule implements RuleInterface
{
    /** EU TAXES */
    public bool $consumer_tax_exempt = false;

    public bool $business_tax_exempt = true;

    public bool $eu_business_tax_exempt = true;

    public bool $foreign_business_tax_exempt = true;

    public bool $foreign_consumer_tax_exempt = true;

    public string $seller_region = '';

    public string $client_region = '';

    public string $client_subregion = '';

    public array $eu_country_codes = [
            'AT', // Austria
            'BE', // Belgium
            'BG', // Bulgaria
            'CY', // Cyprus
            'CZ', // Czech Republic
            'DE', // Germany
            'DK', // Denmark
            'EE', // Estonia
            'ES', // Spain
            'FI', // Finland
            'FR', // France
            'GR', // Greece
            'HR', // Croatia
            'HU', // Hungary
            'IE', // Ireland
            'IT', // Italy
            'LT', // Lithuania
            'LU', // Luxembourg
            'LV', // Latvia
            'MT', // Malta
            'NL', // Netherlands
            'PL', // Poland
            'PT', // Portugal
            'RO', // Romania
            'SE', // Sweden
            'SI', // Slovenia
            'SK', // Slovakia
    ];

    public array $region_codes = [ 
            'AT' => 'EU', // Austria
            'BE' => 'EU', // Belgium
            'BG' => 'EU', // Bulgaria
            'CY' => 'EU', // Cyprus
            'CZ' => 'EU', // Czech Republic
            'DE' => 'EU', // Germany
            'DK' => 'EU', // Denmark
            'EE' => 'EU', // Estonia
            'ES' => 'EU', // Spain
            'FI' => 'EU', // Finland
            'FR' => 'EU', // France
            'GR' => 'EU', // Greece
            'HR' => 'EU', // Croatia
            'HU' => 'EU', // Hungary
            'IE' => 'EU', // Ireland
            'IT' => 'EU', // Italy
            'LT' => 'EU', // Lithuania
            'LU' => 'EU', // Luxembourg
            'LV' => 'EU', // Latvia
            'MT' => 'EU', // Malta
            'NL' => 'EU', // Netherlands
            'PL' => 'EU', // Poland
            'PT' => 'EU', // Portugal
            'RO' => 'EU', // Romania
            'SE' => 'EU', // Sweden
            'SI' => 'EU', // Slovenia
            'SK' => 'EU', // Slovakia
        
            'US' => 'US', // United States

            'AU' => 'AU', // Australia
    ];

    /** EU TAXES */


    /** US TAXES */
    /** US TAXES */

    public string $tax_name1 = '';
    public float $tax_rate1 = 0;

    public string $tax_name2 = '';
    public float $tax_rate2 = 0;

    public string $tax_name3 = '';
    public float $tax_rate3 = 0;

    protected ?Client $client;

    protected ?Response $tax_data;

    public ?Invoice $invoice;
    
    public function __construct()
    {
    }

    public function init(): self
    {
        return $this;
    }

    public function setInvoice(Invoice $invoice): self
    {
        $this->invoice = $invoice;
        
        $this->configTaxData();

        $this->client = $invoice->client;

        $this->tax_data = new Response($this->invoice->tax_data);

        $this->resolveRegions();


        return $this;
    }

    private function configTaxData(): self
    {
        if($this->invoice->tax_data && $this->invoice->status_id > 1)
            return $this;

        //determine if we are taxing locally or if we are taxing globally
        // $this->invoice->tax_data = $this->invoice->client->tax_data;

        return $this;
    }

    // Refactor to support switching between shipping / billing country / region / subregion
    private function resolveRegions(): self
    {

        if(!array_key_exists($this->client->country->iso_3166_2, $this->region_codes))
            throw new \Exception('Automatic tax calculations not supported for this country');

        $this->client_region = $this->region_codes[$this->client->country->iso_3166_2];

        match($this->client_region){
            'US' => $this->client_subregion = $this->tax_data->geoState,
            'EU' => $this->client_subregion = $this->client->country->iso_3166_2,
            default => $this->client_subregion = $this->client->country->iso_3166_2,
        };
    
        return $this;
    }

    public function isTaxableRegion(): bool
    {
        return $this->client->company->tax_data->regions->{$this->client_region}->tax_all_subregions || $this->client->company->tax_data->regions->{$this->client_region}->subregions->{$this->client_subregion}->apply_tax;
    }

    public function defaultForeign(): self
    {

        if($this->client_region == 'US') {
                
            $this->tax_rate1 = $this->tax_data->taxSales * 100;
            $this->tax_name1 = "{$this->tax_data->geoState} Sales Tax";

            return $this;

        }
        elseif($this->client_region == 'AU'){ //these are defaults and are only stubbed out for now, for AU we can actually remove these
            
            $this->tax_rate1 = $this->client->company->tax_data->regions->AU->subregions->AU->tax_rate;
            $this->tax_name1 = $this->client->company->tax_data->regions->AU->subregions->AU->tax_name;

            return $this;
        }

        $this->tax_rate1 = $this->client->company->tax_data->regions->{$this->client_region}->subregions->{$this->client_subregion}->tax_rate;
        $this->tax_name1 = $this->client->company->tax_data->regions->{$this->client_region}->subregions->{$this->client_subregion}->tax_name;

        return $this;
    }

    public function tax($item = null): self
    {
    
        if ($this->client->is_tax_exempt) {
            return $this->taxExempt();
        } elseif($this->client_region == $this->seller_region && $this->isTaxableRegion()) {

            $this->taxByType($item->tax_id);

            return $this;
        } elseif($this->isTaxableRegion()) { //other regions outside of US

            match($item->tax_id) {
                Product::PRODUCT_TYPE_EXEMPT => $this->taxExempt(),
                Product::PRODUCT_TYPE_REDUCED_TAX => $this->taxReduced(),
                Product::PRODUCT_TYPE_OVERRIDE_TAX => $this->override(),
                default => $this->defaultForeign(),
            };

        }
        return $this;

    }
    
    public function taxByType(mixed $type): self
    {
        return $this;
    }

    public function taxReduced(): self
    {
        return $this;
    }

    public function taxExempt(): self
    {
        return $this;
    }

    public function taxDigital(): self
    {
        return $this;
    }

    public function taxService(): self
    {
        return $this;
    }

    public function taxShipping(): self
    {
        return $this;
    }

    public function taxPhysical(): self
    {
        return $this;
    }

    public function default(): self
    {
        return $this;
    }

    public function override(): self
    {
        return $this;
    }

    public function calculateRates(): self
    {
        return $this;
    }
}
