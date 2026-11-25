<?php
require_once __DIR__ . '/../config/env.php';

class MarketDataService {
    private function getJson($url, $headers = []) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $headers
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $status === 200 && $body ? json_decode($body, true) : null;
    }

    public function contextFor($text) {
        if (!preg_match('/invest|aç[oõ]es?|bolsa|b3|fii|etf|tesouro|selic|ipca|cdi|renda fixa|[A-Z]{4}\d{1,2}/iu', $text)) return null;
        $today = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
        $data = ['consulted_at' => $today->format(DATE_ATOM), 'quotes' => [], 'indicators' => [], 'unavailable' => []];
        preg_match_all('/\b([A-Z]{4}\d{1,2})\b/i', $text, $matches);
        $tickers = array_slice(array_unique(array_map('strtoupper', $matches[1])), 0, 3);
        $key = env('BRAPI_API_KEY', '');
        foreach ($tickers as $ticker) {
            if (!$key && !in_array($ticker, ['PETR4', 'MGLU3', 'VALE3', 'ITUB4'], true)) {
                $data['unavailable'][] = "Cotação de {$ticker}: configure BRAPI_API_KEY";
                continue;
            }
            $headers = $key ? ['Authorization: Bearer ' . $key] : [];
            $json = $this->getJson('https://brapi.dev/api/quote/' . $ticker, $headers);
            $quote = $json['results'][0] ?? null;
            if (!$quote || !is_numeric($quote['regularMarketPrice'] ?? null)) {
                $data['unavailable'][] = "Cotação de {$ticker} indisponível";
                continue;
            }
            $marketTime = isset($quote['regularMarketTime']) ? strtotime($quote['regularMarketTime']) : false;
            if ($marketTime && $marketTime > $today->getTimestamp()) {
                $data['unavailable'][] = "Cotação de {$ticker} com data futura; dado descartado";
                continue;
            }
            $data['quotes'][] = [
                'ticker' => $ticker,
                'name' => $quote['longName'] ?? $quote['shortName'] ?? $ticker,
                'price_brl' => (float) $quote['regularMarketPrice'],
                'change_percent' => isset($quote['regularMarketChangePercent']) ? (float) $quote['regularMarketChangePercent'] : null,
                'market_time' => $marketTime ? (new DateTimeImmutable('@' . $marketTime))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format(DATE_ATOM) : null,
                'source' => 'brapi.dev',
                'url' => 'https://brapi.dev/docs/acoes'
            ];
        }
        foreach (['selic_target' => 432, 'ipca_monthly' => 433] as $name => $series) {
            $json = $this->getJson("https://api.bcb.gov.br/dados/serie/bcdata.sgs.{$series}/dados/ultimos/20?formato=json");
            $item = false;
            $indicatorDate = false;
            if (is_array($json)) {
                foreach (array_reverse($json) as $candidate) {
                    $date = isset($candidate['data']) ? DateTimeImmutable::createFromFormat('!d/m/Y', $candidate['data'], new DateTimeZone('America/Sao_Paulo')) : false;
                    if ($date && $date <= $today) { $item = $candidate; $indicatorDate = $date; break; }
                }
            }
            if ($item && isset($item['valor'], $item['data']) && $indicatorDate && $indicatorDate <= $today && is_numeric(str_replace(',', '.', $item['valor']))) {
                $data['indicators'][$name] = [
                    'percent' => (float) str_replace(',', '.', $item['valor']),
                    'date' => $item['data'],
                    'unit' => $name === 'selic_target' ? '% ao ano' : '% no mês',
                    'source' => 'Banco Central do Brasil',
                    'url' => "https://dadosabertos.bcb.gov.br/dataset/{$series}"
                ];
            } else {
                $data['unavailable'][] = "Indicador {$name} indisponível";
            }
        }
        if (!$tickers && preg_match('/aç[oõ]es?|bolsa|b3|fii|etf/iu', $text)) {
            $data['unavailable'][] = 'Nenhum ticker específico informado; não há cotação de ativo para comparar';
        }
        return $data;
    }
}
