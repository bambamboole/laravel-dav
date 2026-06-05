<?php

namespace Bambamboole\LaravelDav\Support;

use Bambamboole\LaravelDav\Dto\AddressBookData;
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

    public function data(): AddressBookData
    {
        return new AddressBookData(
            uri: $this->model->uri,
            displayName: $this->model->display_name,
            description: $this->model->description,
            syncToken: $this->model->sync_token,
        );
    }
}
