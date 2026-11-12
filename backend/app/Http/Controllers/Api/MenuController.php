<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\ModifierGroup;
use App\Models\TaxCategory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $allowedStations = $this->allowedStationsForView($user);
        abort_if($allowedStations === [], 403);

        $canManageMenu = $user->can('manage-menu');
        $includeInactive = $request->boolean('include_inactive') && $canManageMenu;
        $search = mb_strtolower(trim((string) $request->query('search', '')));

        $configureItems = function ($query) use ($includeInactive, $allowedStations, $search, $canManageMenu): void {
            $query
                ->whereIn('station', $allowedStations)
                ->when(! $includeInactive, fn ($q) => $q->where('active', true))
                ->when($search !== '', function ($q) use ($search): void {
                    $q->where(function ($sub) use ($search): void {
                        $sub->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%'])
                            ->orWhereRaw('LOWER(sku) LIKE ?', ['%'.$search.'%'])
                            ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', ['%'.$search.'%']);
                    });
                })
                ->with([
                    'taxCategory',
                    'variants' => fn ($v) => $v
                        ->when(! $includeInactive, fn ($vq) => $vq->where('active', true))
                        ->orderBy('sort_order')
                        ->orderBy('id'),
                    'modifierGroups' => fn ($mg) => $mg
                        ->when(! $includeInactive, fn ($mgq) => $mgq->where('active', true))
                        ->with(['options' => fn ($opt) => $opt
                            ->when(! $includeInactive, fn ($oq) => $oq->where('active', true))
                            ->orderBy('sort_order')
                            ->orderBy('id')])
                        ->orderBy('sort_order')
                        ->orderBy('id'),
                ])
                ->when($canManageMenu, fn ($q) => $q->with([
                    'priceHistories' => fn ($ph) => $ph->with('actor:id,name')->latest('effective_from')->limit(10),
                ]))
                ->orderBy('sort_order')
                ->orderBy('id');
        };

        $categories = MenuCategory::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->when(! $includeInactive, fn ($query) => $query->where('active', true))
            ->with([
                'items' => function ($query) use ($configureItems): void {
                    $query->whereNull('menu_subcategory_id');
                    $configureItems($query);
                },
                'subcategories' => function ($query) use ($includeInactive, $configureItems): void {
                    $query
                        ->when(! $includeInactive, fn ($q) => $q->where('active', true))
                        ->with(['items' => $configureItems])
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $taxCategories = TaxCategory::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('active', true)
            ->orderBy('id')
            ->get();

        $modifierGroups = ModifierGroup::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->when(! $includeInactive, fn ($q) => $q->where('active', true))
            ->with(['options' => fn ($opt) => $opt
                ->when(! $includeInactive, fn ($oq) => $oq->where('active', true))
                ->orderBy('sort_order')
                ->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $categories,
            'meta' => [
                'tax_categories' => $taxCategories,
                'modifier_groups' => $modifierGroups,
                'view_stations' => $allowedStations,
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function allowedStationsForView(User $user): array
    {
        if (
            $user->can('manage-menu')
            || $user->can('manage-orders')
            || $user->can('view-floor')
            || $user->can('take-payment')
        ) {
            return ['KITCHEN', 'BAR'];
        }

        $stations = [];
        if ($user->can('manage-kitchen')) {
            $stations[] = 'KITCHEN';
        }
        if ($user->can('manage-bar')) {
            $stations[] = 'BAR';
        }

        return $stations;
    }
}
