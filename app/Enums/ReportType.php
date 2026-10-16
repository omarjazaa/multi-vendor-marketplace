<?php

namespace App\Enums;

enum ReportType: string
{
    case SALES_SUMMARY = 'sales_summary';
    case BEST_SELLERS = 'best_sellers';
    case REVENUE_TREND = 'revenue_trend';
}
