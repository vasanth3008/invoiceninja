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

namespace App\Utils\Traits;

use App\DataMapper\CompanySettings;
use App\Models\Company;
use stdClass;

/**
 * Class CompanySettingsSaver.
 *
 * Whilst it may appear that this CompanySettingsSaver and ClientGroupSettingsSaver
 * could be duplicates, they are not.
 *
 * Each requires their own approach to saving and attempts to
 * merge the two code paths should be avoided.
 */
trait CompanySettingsSaver
{
    /**
     * Saves a setting object.
     *
     * Works for groups|clients|companies
     * @param  array $settings The request input settings array
     * @param  object $entity   The entity which the settings belongs to
     * @return void
     */
    public function saveSettings($settings, $entity)
    {
        /* No Settings, No Save!*/
        if (! $settings) {
            return;
        }

        //Unset Protected Properties.
        foreach (CompanySettings::$protected_fields as $field) {
            unset($settings[$field]);
        }

        $settings = $this->checkSettingType($settings);

        $company_settings = CompanySettings::defaults();

        foreach ($settings as $key => $value) {
            if (is_null($settings->{$key})) {
                $company_settings->{$key} = '';
            } else {
                $company_settings->{$key} = $value;
            }
        }

        //this pass will handle any null values that are in the translations
        foreach ($settings->translations as $key => $value) {
            if (is_null($settings->translations[$key])) {
                $settings->translations[$key] = '';
            }
        }

        $company_settings->translations = $settings->translations;

        $entity->settings = $company_settings;

        $entity->save();
    }

    /**
     * Used for custom validation of inbound
     * settings request.
     *
     * Returns an array of errors, or boolean TRUE
     * on successful validation
     * @param  array $settings The request() settings array
     * @return array|bool      Array on failure, boolean TRUE on success
     */
    public function validateSettings($settings)
    {
        $settings = (object) $settings;

        $casts = CompanySettings::$casts;

        ksort($casts);

        foreach ($casts as $key => $value) {
            if (in_array($key, CompanySettings::$string_casts)) {
                $value = 'string';

                if (! property_exists($settings, $key)) {
                    continue;
                } elseif (! $this->checkAttribute($value, $settings->{$key})) {
                    return [$key, $value, $settings->{$key}];
                }

                continue;
            }
            /*Separate loop if it is a _id field which is an integer cast as a string*/
            elseif (substr($key, -3) == '_id' || substr($key, -14) == 'number_counter') {
                $value = 'integer';

                if (! property_exists($settings, $key)) {
                    continue;
                } elseif (! $this->checkAttribute($value, $settings->{$key})) {
                    return [$key, $value, $settings->{$key}];
                }

                continue;
            } elseif ($key == 'pdf_variables') {
                continue;
            }

            /* Handles unset settings or blank strings */
            if (! property_exists($settings, $key) || is_null($settings->{$key}) || ! isset($settings->{$key}) || $settings->{$key} == '') {
                continue;
            }

            /*Catch all filter */
            if (! $this->checkAttribute($value, $settings->{$key})) {
                return [$key, $value, $settings->{$key}];
            }
        }

        return true;
    }

    /**
     * Checks the settings object for
     * correct property types.
     *
     * The method will drop invalid types from
     * the object and will also settype() the property
     * so that it can be saved cleanly
     *
     * @param  array $settings The settings request() array
     * @return stdClass       stdClass object
     */
    private function checkSettingType($settings) : stdClass
    {
        $settings = (object) $settings;

        $casts = CompanySettings::$casts;

        foreach ($casts as $key => $value) {
            if (in_array($key, CompanySettings::$string_casts)) {
                $value = 'string';

                if (! property_exists($settings, $key)) {
                    continue;
                } elseif ($this->checkAttribute($value, $settings->{$key})) {
                    if (substr($key, -3) == '_id') {
                        settype($settings->{$key}, 'string');
                    } else {
                        settype($settings->{$key}, $value);
                    }
                } else {
                    unset($settings->{$key});
                }

                continue;
            }
            /*Separate loop if it is a _id field which is an integer cast as a string*/
            if (substr($key, -3) == '_id' || substr($key, -14) == 'number_counter') {
                $value = 'integer';

                if (! property_exists($settings, $key)) {
                    continue;
                } elseif ($this->checkAttribute($value, $settings->{$key})) {
                    if (substr($key, -3) == '_id') {
                        settype($settings->{$key}, 'string');
                    } else {
                        settype($settings->{$key}, $value);
                    }
                } else {
                    unset($settings->{$key});
                }

                continue;
            } elseif ($key == 'pdf_variables') {
                settype($settings->{$key}, 'object');
            }

            /* Handles unset settings or blank strings */
            if (! property_exists($settings, $key) || is_null($settings->{$key}) || ! isset($settings->{$key}) || $settings->{$key} == '') {
                continue;
            }

            /*Catch all filter */
            if ($this->checkAttribute($value, $settings->{$key})) {
                if ($value == 'string' && is_null($settings->{$key})) {
                    $settings->{$key} = '';
                }

                settype($settings->{$key}, $value);
            } else {
                unset($settings->{$key});
            }
        }

        return $settings;
    }

    /**
     * Type checks a object property.
     * @param  string $key   The type
     * @param  string $value The object property
     * @return bool        TRUE if the property is the expected type
     */
    private function checkAttribute($key, $value) :bool
    {
        switch ($key) {
            case 'int':
            case 'integer':
                if(is_string($value))
                    return false;

                return is_int($value) || ctype_digit(strval(abs($value)));
            case 'real':
            case 'float':
            case 'double':
                return is_float($value) || is_numeric(strval($value));
            case 'string':
                return method_exists($value, '__toString') || is_null($value) || is_string($value);
                //return is_null($value) || is_string($value);
            case 'bool':
            case 'boolean':
                return is_bool($value) || (int) filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'object':
                return is_object($value);
            case 'array':
                return is_array($value);
            case 'json':
                json_decode($value);

                    return json_last_error() == JSON_ERROR_NONE;
            default:
                return false;
        }
    }

    private function getAccountFromEntity($entity)
    {
        if ($entity instanceof Company) {
            return $entity->account;
        }

        return $entity->company->account;
    }
}
