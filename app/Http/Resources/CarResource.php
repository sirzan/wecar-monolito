<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $title = trim(implode(' ', array_filter([
            $this->brand?->name,
            $this->model?->name,
            $this->year,
        ])));

        $gallery = $this->gallery ?? [];
        $image = $this->image ?? ($gallery[0] ?? null);

        return [
            'id' => $this->getKey(),
            'slug' => $this->slug,
            'title' => $title,
            'brand_id' => $this->brand_id,
            'brand' => $this->brand?->name,
            'model_id' => $this->model_id,
            'model' => $this->model?->name,
            'version' => $this->version?->name,
            'year' => $this->year,
            'km' => (int) $this->km,
            'price' => (float) $this->price,
            'previous_price' => $this->previous_price ? (float) $this->previous_price : null,
            'city' => $this->city,
            'state' => $this->state,
            'description' => $this->description,
            'image' => $image,
            'gallery' => array_values($gallery),
            'transmission' => $this->transmission,
            'fuel' => $this->fuel,
            'body' => $this->body,
            'color' => $this->color,
            'featured' => (bool) $this->featured,
            'verified' => filled($this->vin) || filled($this->serial_number) || filled($this->plates),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'accepts_trade' => (bool) $this->accepts_trade,
            'trade_preference' => $this->trade_preference,
            'status' => $this->status?->name,
            'status_label' => $this->status?->label,
            'plan' => $this->plan,
            'owner_name' => $this->whenLoaded('owner', fn () => $this->owner?->name),
            'agency' => $this->whenLoaded('agency', fn () => $this->agency ? [
                'id' => $this->agency->getKey(),
                'slug' => $this->agency->slug,
                'name' => $this->agency->name,
                'logo' => $this->agency->logo,
                'city' => $this->agency->city,
                'phone' => $this->agency->phone,
                'whatsapp' => $this->agency->whatsapp,
            ] : null),
            'published_at' => $this->published_at?->toIso8601String(),
            'url' => route('autos.show', $this->slug),
        ];
    }
}
