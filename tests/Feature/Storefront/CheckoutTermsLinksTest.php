<?php

namespace Tests\Feature\Storefront;

use App\Models\ContentPage;
use Tests\Feature\Pricing\PricingTestCase;

class CheckoutTermsLinksTest extends PricingTestCase
{
    private function checkoutHtml(): string
    {
        $this->makeCourier();
        $cart = $this->makeCart([[$this->makeVariant(5000, stock: 5), 1]]);

        return $this->withSession(['cart_ulid' => $cart->ulid])->get(route('checkout.index'))->assertOk()->getContent();
    }

    public function test_terms_and_privacy_are_links_when_the_pages_are_published(): void
    {
        foreach ([ContentPage::TERMS => 'Termeni', ContentPage::PRIVACY => 'Confidențialitate'] as $slug => $title) {
            ContentPage::create(['title' => $title, 'slug' => $slug, 'content' => 'x', 'is_published' => true]);
        }

        $html = $this->checkoutHtml();

        $this->assertStringContainsString('href="'.route('pages.show', ContentPage::TERMS).'"', $html);
        $this->assertStringContainsString('href="'.route('pages.show', ContentPage::PRIVACY).'"', $html);
    }

    public function test_without_published_pages_the_text_stays_plain(): void
    {
        ContentPage::create(['title' => 'Termeni', 'slug' => ContentPage::TERMS, 'content' => 'x', 'is_published' => false]);

        $html = $this->checkoutHtml();

        $this->assertStringContainsString('termenii și condițiile', $html);
        $this->assertStringNotContainsString('/pagina/', $html);
    }
}
