<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Client;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class DeviceInfo extends Component
{
    use WithPagination;

    public $client = '';
    public $clients = [];
    public $devices;
    public $error = '';
    public $perPage = 50;
    public $timezone = 'America/New_York';
    public $page = 1; // Explicitly initialize $page

    protected $paginationTheme = 'tailwind';

    public function mount()
    {
        Log::info('Mount called', ['client' => $this->client]);
        $this->clients = Client::pluck('name')->toArray();
        $this->devices = collect([]);
        $this->client = !empty($this->clients) ? $this->clients[0] : '';
        if ($this->client) {
            Log::info('Fetching devices on mount', ['client' => $this->client]);
            $this->fetchDevices();
        }
    }

    public function updatedClient()
    {
        Log::info('Client updated', ['client' => $this->client]);
        $this->resetPage();
        if ($this->client) {
            $this->fetchDevices();
        } else {
            $this->devices = collect([]);
            $this->error = 'Please select a client';
        }
    }

    public function updatedTimezone()
    {
        // No need to re-fetch, timezone is handled in view
        Log::info('Timezone updated', ['timezone' => $this->timezone]);
    }

    public function fetchDevices()
    {
        Log::info('fetchDevices called', ['client' => $this->client]);
        $this->devices = collect([]);
        $this->error = '';

        if (empty($this->client)) {
            $this->error = 'Please select a client';
            Log::warning('No client selected');
            return;
        }

        try {
            $cacheKey = 'devices_' . $this->client;
            $cacheTTL = now()->addMinutes(10);

            $this->devices = Cache::remember($cacheKey, $cacheTTL, function () {
                $url = env('DEVICE_API_URL');
                if (!$url) {
                    Log::error('DEVICE_API_URL not set');
                    throw new \Exception('API URL not configured');
                }
                $response = Http::timeout(10)->get($url, [
                    'client' => $this->client
                ]);
                Log::info('API Response', [
                    'url' => $url . '?client=' . $this->client,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                if ($response->successful()) {
                    $data = $response->json();
                    return collect(is_array($data) ? $data : []);
                }
                Log::error('API request failed', ['status' => $response->status()]);
                throw new \Exception('API request failed with status: ' . $response->status());
            });

            if ($this->devices->isEmpty()) {
                $this->error = 'No devices found for client: ' . $this->client;
                Log::warning('No devices found', ['client' => $this->client]);
            }
        } catch (\Exception $e) {
            $this->error = 'Error fetching devices: ' . $e->getMessage();
            $this->devices = collect([]);
            Log::error('API Request Exception', [
                'error' => $e->getMessage(),
                'client' => $this->client
            ]);
        }
    }

    public function render()
    {
        Log::info('Render called', ['client' => $this->client, 'page' => $this->page]);
        $paginatedDevices = new \Illuminate\Pagination\LengthAwarePaginator(
            $this->devices->forPage($this->page ?? 1, $this->perPage),
            $this->devices->count(),
            $this->perPage,
            $this->page ?? 1,
            ['path' => route('device-info')]
        );

        return view('livewire.device-info', [
            'paginatedDevices' => $paginatedDevices,
            'totalDevices' => $this->devices->count(),
            'timezone' => $this->timezone
        ])->layout('layouts.app');
    }
}