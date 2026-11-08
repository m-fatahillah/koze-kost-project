<?php

namespace App\Enums;

enum PriceType: string
{
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';
    case DAILY = 'daily';
}
