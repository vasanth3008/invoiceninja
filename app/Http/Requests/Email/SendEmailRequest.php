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

namespace App\Http\Requests\Email;

use App\Http\Requests\Request;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

class SendEmailRequest extends Request
{
    use MakesHash;

    private string $error_message = '';
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return $this->checkUserAbleToSend();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'template' => 'bail|required',
            'entity' => 'bail|required',
            'entity_id' => 'bail|required',
            'cc_email.*' => 'bail|sometimes|email',
        ];


    }

    public function prepareForValidation()
    {
        $input = $this->all();

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $settings = $user->company()->settings;

        if (empty($input['template'])) {
            $input['template'] = '';
        }

        if (! property_exists($settings, $input['template'])) {
            unset($input['template']);
        }

        if (array_key_exists('entity_id', $input)) {
            $input['entity_id'] = $this->decodePrimaryKey($input['entity_id']);
        }
        
        if (isset($input['entity'])) {
            $input['entity'] = "App\Models\\".ucfirst(Str::camel($input['entity']));
        }

        if(isset($input['cc_email'])) {
            $input['cc_email'] = collect(explode(",", $input['cc_email']))->map(function ($email) {
                return trim($email);
            })->filter(function ($email) {
                return filter_var($email, FILTER_VALIDATE_EMAIL);
            })->slice(0, 4)->toArray();
        }

        $this->replace($input);
    }

    public function message()
    {
        return [
            'template' => 'Invalid template.',
        ];
    }

    private function checkUserAbleToSend()
    {
        $input = $this->all();

        /** @var \App\Models\User $user */
        $user = auth()->user();
        
        if (Ninja::isHosted() && !$user->account->account_sms_verified) {
            $this->error_message = ctrans('texts.authorization_sms_failure');
            return false;
        }
        
        if (Ninja::isHosted() && $user->account->emailQuotaExceeded()) {
            $this->error_message = ctrans('texts.email_quota_exceeded_subject');
            return false;
        }

        /*Make sure we have all the require ingredients to send a template*/
        if (isset($input['entity']) && array_key_exists('entity_id', $input) && is_string($input['entity']) && $input['entity_id']) {


            $company = $user->company();

            $entity = $input['entity'];

            /* Harvest the entity*/
            $entity_obj = $entity::whereId($input['entity_id'])->withTrashed()->company()->first();

            /* Check object, check user and company id is same as users, and check user can edit the object */
            if ($entity_obj && ($company->id == $entity_obj->company_id) && $user->can('edit', $entity_obj)) {
                return true;
            }
        } else {
            $this->error_message = "Invalid entity or entity_id";
        }

        return false;
    }

    protected function failedAuthorization()
    {
        throw new AuthorizationException($this->error_message);
    }
}
