<?php

declare(strict_types=1);

namespace Frosh\GMVViewer\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
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

        $context = Context::createDefaultContext();
        $liveVersionUUID = $context->getVersionId();

        $sql = "
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
    ";

        $list = $this->connection->executeQuery($sql, [
            'start' => $start,
            'end' => $end,
            'liveVersionId' => Uuid::fromHexToBytes($liveVersionUUID),
        ])->fetchAllAssociative();

        // Bestimme die Standardwährung (Faktor = 1)
        $defaultCurrency = null;
        foreach ($list as $entry) {
            if ($entry['currency_factor'] == 1) {
                $defaultCurrency = $entry['currency_iso_code'];
                break;
            }
        }

        // Berechne Jahreswerte (Vorjahr, aktuelles Jahr)
        $gmvYearly = [];
        foreach ($list as $entry) {
            $year = substr($entry['date'], 0, 4);
            $currencyIsoCode = $entry['currency_iso_code'];
            $key = $year . '_' . $currencyIsoCode;

            if (!isset($gmvYearly[$key])) {
                $gmvYearly[$key] = [
                    'date' => $year,
                    'turnover_total' => 0,
                    'turnover_net' => 0,
                    'order_count' => 0,
                    'currency_iso_code' => $currencyIsoCode,
                    'currency_factor' => $entry['currency_factor'],
                    'converted_total' => 0,
                    'converted_net' => 0,
                ];
            }

            $gmvYearly[$key]['turnover_total'] += $entry['turnover_total'];
            $gmvYearly[$key]['turnover_net'] += $entry['turnover_net'];
            $gmvYearly[$key]['order_count'] += $entry['order_count'];
            $gmvYearly[$key]['converted_total'] += $entry['turnover_total'] / $entry['currency_factor'];
            $gmvYearly[$key]['converted_net'] += $entry['turnover_net'] / $entry['currency_factor'];
        }

        // Erzeuge Referenzliste der letzten 12 Monate (ab heute rückwärts)
        $monthRange = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthRange[] = date('Y-m', strtotime("-$i months"));
        }

        // Initialisiere Monatsstruktur für jede Währung
        $monthlyDataByCurrency = [];

        // Erfasse alle Währungen, die vorkommen
        $currencies = [];
        foreach ($list as $entry) {
            $currencies[$entry['currency_iso_code']] = true;
        }

        // Befülle vorhandene Werte
        foreach ($list as $entry) {
            $month = $entry['date'];
            if (!in_array($month, $monthRange)) {
                continue; // Nur letzte 12 Monate berücksichtigen
            }

            $currency = $entry['currency_iso_code'];

            // Falls Währung nicht initialisiert wurde (zur Sicherheit)
            if (!isset($monthlyDataByCurrency[$currency][$month])) {
                $monthlyDataByCurrency[$currency][$month] = [
                    'turnover_total' => 0,
                    'turnover_net' => 0,
                    'order_count' => 0,
                    'currency_iso_code' => $currency,
                    'currency_factor' => $entry['currency_factor'],
                    'converted_total' => 0,
                    'converted_net' => 0,
                ];
            }

            $monthlyDataByCurrency[$currency][$month]['turnover_total'] += $entry['turnover_total'];
            $monthlyDataByCurrency[$currency][$month]['turnover_net'] += $entry['turnover_net'];
            $monthlyDataByCurrency[$currency][$month]['order_count'] += $entry['order_count'];
            $monthlyDataByCurrency[$currency][$month]['converted_total'] += $entry['turnover_total'] / $entry['currency_factor'];
            $monthlyDataByCurrency[$currency][$month]['converted_net'] += $entry['turnover_net'] / $entry['currency_factor'];
        }

        // Sortiere Monatsdaten pro Währung chronologisch
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
