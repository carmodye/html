<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Client;

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
            // Fetch devices for the selected client
            $response = Http::timeout(10)->get(env('DEVICE_API_URL'), [
                'client' => $this->client
            ]);

            // Log the raw response for debugging
            Log::info('API Response', [
                'url' => env('DEVICE_API_URL') . '?client=' . $this->client,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->devices = is_array($data) ? $data : [];
                if (empty($this->devices)) {
                    $this->error = 'No devices found for client: ' . $this->client;
                }
            } else {
                $this->error = 'API request failed with status: ' . $response->status();
            }
        } catch (\Exception $e) {
            $this->error = 'Error fetching devices: ' . $e->getMessage();
            $this->devices = [];
            Log::error('API Request Exception', [
                'error' => $e->getMessage(),
                'client' => $this->client
            ]);
        }
    }

    public function render()
    {
        return view('livewire.device-info', [
            'devices' => $this->devices
        ])->layout('layouts.app');
    }
}