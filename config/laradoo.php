<?php

return [
    'api-suffix' => 'xmlrpc',     // 'xmlrpc' from version 7.0 and earlier, 'xmlrpc/2' from version 8.0 and above.

    //Credentials
    'host' => env('ODOO_HOST'),  // should contain 'http://' or 'https://'
    'db' => env('ODOO_DB'),
    'username' => env('ODOO_USERNAME'),
    'password' => env('ODOO_PASSWORD'),
];
