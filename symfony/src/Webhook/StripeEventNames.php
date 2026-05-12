<?php

declare(strict_types=1);

namespace App\Webhook;

final class StripeEventNames
{
    public const string PRICE_CREATED = 'stripe.price.created';
    public const string PRICE_UPDATED = 'stripe.price.updated';
    public const string PRICE_DELETED = 'stripe.price.deleted';
    public const string PRODUCT_UPDATED = 'stripe.product.updated';
    public const string CHECKOUT_SESSION_EXPIRED = 'stripe.checkout.session.expired';
    public const string CHECKOUT_SESSION_COMPLETED = 'stripe.checkout.session.completed';
}
