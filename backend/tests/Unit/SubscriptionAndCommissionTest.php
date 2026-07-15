<?php

namespace Tests\Unit;

use App\Services\OrderWorkflowService;
use App\Services\SubscriptionService;
use App\Support\FedapayClient;
use Tests\TestCase;

class SubscriptionAndCommissionTest extends TestCase
{
    public function test_plans_config_matches_cdc(): void
    {
        $this->assertSame(2, SubscriptionService::servicesMax('gratuit'));
        $this->assertSame(10, SubscriptionService::servicesMax('starter'));
        $this->assertNull(SubscriptionService::servicesMax('pro'));

        $this->assertSame(0, SubscriptionService::priority('gratuit'));
        $this->assertSame(1, SubscriptionService::priority('starter'));
        $this->assertSame(2, SubscriptionService::priority('pro'));

        $this->assertSame(3500, (int) SubscriptionService::planConfig('starter')['prix']);
        $this->assertSame(6000, (int) SubscriptionService::planConfig('pro')['prix']);
    }

    public function test_commission_is_zero(): void
    {
        $svc = new OrderWorkflowService(
            $this->createMock(FedapayClient::class),
            $this->createMock(\App\Services\OrderEventNotifier::class),
        );

        $fees = $svc->computeCommission(100000);
        $this->assertSame(0, $fees['commission_fcfa']);
        $this->assertSame(100000, $fees['net_prestataire_fcfa']);
    }
}
