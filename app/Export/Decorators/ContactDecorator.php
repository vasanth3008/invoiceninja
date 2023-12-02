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

namespace App\Export\Decorators;

use App\Models\ClientContact;

class ContactDecorator implements DecoratorInterface
{
    public function transform(string $key, mixed $entity): mixed
    {
        $contact = false;

        if($entity instanceof ClientContact) {
            $contact = $entity;
        } elseif($entity->contacts) {
            $contact = $entity->contacts()->first();
        }

        if($contact && method_exists($this, $key)) {
            return $this->{$key}($contact);
        }
        elseif($contact && $contact->{$key}){
            return $contact->{$key};
        }

        return '';

    }

}
