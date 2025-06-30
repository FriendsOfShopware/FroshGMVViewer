<?php

declare(strict_types=1);

namespace Frosh\GMVViewer\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/_action/frosh-gmv', defaults: ['_routeScope' => ['api']])]
class GmvController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
    )
    {
    }

    #[Route(path: '/gmv/list', name: 'api.frosh.tools.gmv.list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $start = date('Y-01-01', strtotime('-1 year'));
        $end = date('Y-m-d');

        $sql = <<<'SQL'
            SELECT ROUND(SUM(`order`.`amount_total`), 2) AS `turnover_total`,
                   ROUND(SUM(`order`.`amount_net`), 2) AS `turnover_net`,
                   COUNT(`order`.`id`) AS `order_count`,
                   DATE_FORMAT(`order`.`order_date`, '%Y-%m') AS `date`,
                   `currency`.`iso_code` AS `currency_iso_code`,
                   `currency`.`factor` AS `currency_factor`
            FROM `order`
            INNER JOIN `currency` on `order`.currency_id = `currency`.`id`
            WHERE `order`.`order_date` BETWEEN :start AND :end
              AND `order`.`version_id` = :liveVersionId
              AND (JSON_CONTAINS(`order`.`custom_fields`, 'true', '$.saas_test_order') IS NULL
                   OR JSON_CONTAINS(`order`.`custom_fields`, 'true', '$.saas_test_order') = 0)
            GROUP BY DATE_FORMAT(`order`.`order_date`, '%Y-%m'), `order`.`currency_id`
            SQL;

        $list = $this->connection->executeQuery($sql, [
            'start' => $start,
            'end' => $end,
            'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ])->fetchAllAssociative();

        // Bestimme die Standardwährung (Faktor = 1)
        $defaultCurrency = null;
        foreach ($list as $entry) {
            if ($entry['currency_factor'] == 1) {
                $defaultCurrency = $entry['currency_iso_code'];
                break;
            }
        }

        $gmvYearly = [];
        $monthlyDataByCurrency = [];

        foreach ($list as $entry) {
            $currencyIsoCode = $entry['currency_iso_code'];

            // `date` is in the format `YYYY-mm`
            $date = $entry['date'];

            $year = substr($date, 0, 4);
            $key = $year . '_' . $currencyIsoCode;

            // Yearly data
            $gmvYearly[$key] ??= [
                'date' => $year,
                'turnover_total' => 0,
                'turnover_net' => 0,
                'order_count' => 0,
                'currency_iso_code' => $currencyIsoCode,
                'currency_factor' => $entry['currency_factor'],
                'converted_total' => 0,
                'converted_net' => 0,
            ];

            $gmvYearly[$key]['turnover_total'] += $entry['turnover_total'];
            $gmvYearly[$key]['turnover_net'] += $entry['turnover_net'];
            $gmvYearly[$key]['order_count'] += $entry['order_count'];
            $gmvYearly[$key]['converted_total'] += $entry['turnover_total'] / $entry['currency_factor'];
            $gmvYearly[$key]['converted_net'] += $entry['turnover_net'] / $entry['currency_factor'];

            // Monthly data
            $monthlyDataByCurrency[$currencyIsoCode][$date] ??= [
                'turnover_total' => 0,
                'turnover_net' => 0,
                'order_count' => 0,
                'currency_iso_code' => $currencyIsoCode,
                'currency_factor' => $entry['currency_factor'],
                'converted_total' => 0,
                'converted_net' => 0,
            ];

            $monthlyDataByCurrency[$currencyIsoCode][$date]['turnover_total'] += $entry['turnover_total'];
            $monthlyDataByCurrency[$currencyIsoCode][$date]['turnover_net'] += $entry['turnover_net'];
            $monthlyDataByCurrency[$currencyIsoCode][$date]['order_count'] += $entry['order_count'];
            $monthlyDataByCurrency[$currencyIsoCode][$date]['converted_total'] += $entry['turnover_total'] / $entry['currency_factor'];
            $monthlyDataByCurrency[$currencyIsoCode][$date]['converted_net'] += $entry['turnover_net'] / $entry['currency_factor'];
        }

        foreach ($monthlyDataByCurrency as &$currencyData) {
            ksort($currencyData);
        }

        return new JsonResponse([
            'month' => $monthlyDataByCurrency,
            'year' => $gmvYearly,
            'defaultCurrency' => $defaultCurrency,
        ]);
    }
}
