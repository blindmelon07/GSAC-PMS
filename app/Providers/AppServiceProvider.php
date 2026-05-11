<?php

namespace App\Providers;

use App\Models\FormOrder;
use App\Models\Invoice;
use App\Policies\FormOrderPolicy;
use App\Policies\InvoicePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider;

class AppServiceProvider extends AuthServiceProvider
{
    protected $policies = [
        FormOrder::class => FormOrderPolicy::class,
        Invoice::class   => InvoicePolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
