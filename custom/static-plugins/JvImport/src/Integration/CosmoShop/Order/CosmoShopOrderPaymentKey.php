<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Order;

enum CosmoShopOrderPaymentKey: string
{
    case AMAZON_PAY = 'amazon_pay';
    case CASH_ON_DELIVERY = 'cash_on_delivery';
    case EASYCREDIT = 'easycredit';
    case INSTALLMENT_PURCHASE = 'installment_purchase';
    case INVOICE = 'invoice';
    case KLARNA = 'klarna';
    case KLARNA_PAY_LATER = 'klarna_pay_later';
    case KLARNA_PAY_NOW = 'klarna_pay_now';
    case KLARNA_PAYMENTS = 'klarna_payments';
    case PAYPAL = 'paypal';
    case PAYPAL_EXPRESS = 'paypal_express';
    case PREPAYMENT_DISCOUNT = 'prepayment_discount';
    case SANTANDER_FINANCING = 'santander_financing';
    case SKRILL = 'skrill';
    case SPLIT_DEPOSIT = 'split_deposit';
}
