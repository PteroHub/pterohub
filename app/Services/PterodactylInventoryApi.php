<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use App\Support\PterohubPterodactylConfig as ApiConfig;
use RuntimeException;
final class PterodactylInventoryApi {
    public function pages(string $endpoint): array {
        if (!in_array($endpoint, ['nodes','servers','allocations'], true)) throw new RuntimeException('Unsupported endpoint');
        $url = ApiConfig::url();
        $key = ApiConfig::apiKey();
        if (!str_starts_with($url, 'https://') || !$key) throw new RuntimeException('API configuration missing');
        $rows = [];
        for ($page=1; $page<=100; $page++) {
            $response = Http::withToken($key)->accept('application/vnd.pterodactyl.v1+json')
                ->timeout(20)->get($url.'/api/application/'.$endpoint, ['page'=>$page,'per_page'=>100]);
            if (!$response->successful()) throw new RuntimeException('Endpoint returned HTTP '.$response->status());
            $json = $response->json();
            if (!is_array($json) || !isset($json['data']) || !is_array($json['data'])) throw new RuntimeException('Invalid API schema');
            foreach ($json['data'] as $item) if (isset($item['attributes']) && is_array($item['attributes'])) $rows[]=$item['attributes'];
            $total = data_get($json,'meta.pagination.total_pages');
            if (!$total || $page >= (int)$total) return $rows;
        }
        throw new RuntimeException('Pagination safety limit');
    }
}
