<?php

namespace Slakbal\TenancyFoundation\Events\Contact;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Slakbal\TenancyFoundation\Models\ContactRequest;

class ContactRequestAnswered
{
    use Dispatchable, SerializesModels;

    public function __construct(public ContactRequest $contactRequest) {}
}
