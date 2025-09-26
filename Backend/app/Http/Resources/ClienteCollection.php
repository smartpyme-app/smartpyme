<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ClienteCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray($request): array
    {
        return [
            'current_page' => $this->currentPage(),
            'data' => $this->collection,
            'first_page_url' => $this->url(1),
            'from' => $this->firstItem(),
            'last_page' => $this->lastPage(),
            'last_page_url' => $this->url($this->lastPage()),
            'links' => $this->linkCollection(),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path(),
            'per_page' => $this->perPage(),
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->lastItem(),
            'total' => $this->total(),
        ];
    }

    /**
     * Create the link collection for pagination.
     */
    private function linkCollection(): array
    {
        return collect($this->elements())->map(function ($element, $key) {
            if (is_string($element)) {
                return [
                    'url' => null,
                    'label' => $element,
                    'active' => false,
                ];
            }

            if (is_array($element)) {
                return collect($element)->map(function ($url, $page) {
                    return [
                        'url' => $url,
                        'label' => (string) $page,
                        'active' => $page == $this->currentPage(),
                    ];
                });
            }

            return [];
        })->flatten(1)->toArray();
    }
}