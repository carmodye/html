<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Client;
use Illuminate\Support\Facades\Cache;


class DeviceInfo extends Component
{
    public $client = '';
    public $clients = [];
    public $devices = [];
    public $error = '';

    public function mount()
    {
        // Fetch clients from the database
        $this->clients = Client::pluck('name')->toArray();

        // Set default client to the first one, if available
        $this->client = !empty($this->clients) ? $this->clients[0] : '';

        // Fetch devices only if a client is selected
        if ($this->client) {
            $this->fetchDevices();
        }
    }

    public function updatedClient()
    {
        // Fetch devices only if a client is selected
        if ($this->client) {
            $this->fetchDevices();
        } else {
            $this->devices = [];
            $this->error = 'Please select a client';
        }
    }



    public function fetchDevices()
    {
        $this->devices = [];
        $this->error = '';

        if (empty($this->client)) {
            $this->error = 'Please select a client';
            return;
        }

        try {
            $cacheKey = 'devices_' . $this->client;
            $cacheTTL = now()->addMinutes(10);

            $this->devices = Cache::remember($cacheKey, $cacheTTL, function () {
                $response = Http::timeout(10)->get(env('DEVICE_API_URL'), [
                    'client' => $this->client
                ]);
                // Log::info('API Response', [
                //     'url' => env('DEVICE_API_URL') . '?client=' . $this->client,
                //     'status' => $response->status(),
                //     'body' => $response->body()
                // ]);
                if ($response->successful()) {
                    $data = $response->json();
                    return is_array($data) ? $data : [];
                }
                return [];
            });

            if (empty($this->devices)) {
                $this->error = 'No devices found for client: ' . $this->client;
            }
        } catch (\Exception $e) {
            $this->error = 'Error fetching devices: ' . $e->getMessage();
            $this->devices = [];
            Log::error('API Request Exception', ['error' => $e->getMessage(), 'client' => $this->client]);
        }
    }

    public function render()
    {
        return view('livewire.device-info', [
            'devices' => $this->devices
        ])->layout('layouts.app');
    }
}