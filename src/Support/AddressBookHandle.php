<?php

namespace Bambamboole\LaravelDav\Support;

use Bambamboole\LaravelDav\Models\DavAddressBook;

class AddressBookHandle
{
    public function __construct(
        private ContactCardWriter $contacts,
        public DavAddressBook $model,
    ) {}

    public function contacts(): ContactCardScope
    {
        return new ContactCardScope($this->contacts, $this->model->user_id, $this->model);
    }
}
