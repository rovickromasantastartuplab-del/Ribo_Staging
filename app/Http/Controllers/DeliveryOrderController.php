<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Models\SalesOrder;
use App\Models\Account;
use App\Models\Contact;
use App\Models\Product;
use App\Models\ShippingProviderType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DeliveryOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = DeliveryOrder::query()
            ->with(['salesOrder', 'account', 'contact', 'shippingProviderType', 'creator', 'assignedUser', 'products'])
            ->where(function($q) {
                if (auth()->user()->type === 'company') {
                    $q->where('created_by', createdBy());
                } else {
                    $q->where('assigned_to', auth()->id());
                }
            });

        if ($request->has('search') && !empty($request->search)) {
            $query->where(function($q) use ($request) {
                $q->where('delivery_number', 'like', '%' . $request->search . '%')
                  ->orWhere('name', 'like', '%' . $request->search . '%')
                  ->orWhereHas('account', fn($q) => $q->where('name', 'like', '%' . $request->search . '%'));
            });
        }

        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('account_id') && !empty($request->account_id) && $request->account_id !== 'all') {
            $query->where('account_id', $request->account_id);
        }

        if ($request->has('sales_order_id') && !empty($request->sales_order_id) && $request->sales_order_id !== 'all') {
            $query->where('sales_order_id', $request->sales_order_id);
        }

        if ($request->has('assigned_to') && !empty($request->assigned_to) && $request->assigned_to !== 'all') {
            if ($request->assigned_to === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $request->assigned_to);
            }
        }

        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $deliveryOrders = $query->paginate($request->per_page ?? 10);

        $users = [];
        if (auth()->user()->type === 'company') {
            $users = \App\Models\User::where('created_by', createdBy())
                ->select('id', 'name', 'email')
                ->get();
        }

        return Inertia::render('delivery-orders/index', [
            'deliveryOrders' => $deliveryOrders,
            'accounts' => Account::where('created_by', createdBy())
                ->where('status', 'active')
                ->when(auth()->user()->type !== 'company', function($q) {
                    $q->where('assigned_to', auth()->id());
                })
                ->select('id', 'name')->get(),
            'contacts' => Contact::where('created_by', createdBy())
                ->where('status', 'active')
                ->when(auth()->user()->type !== 'company', function($q) {
                    $q->where('assigned_to', auth()->id());
                })
                ->select('id', 'name')->get(),
            'salesOrders' => SalesOrder::where('created_by', createdBy())
                ->when(auth()->user()->type !== 'company', function($q) {
                    $q->where('assigned_to', auth()->id());
                })
                ->select('id', 'name', 'order_number')->get(),
            'products' => $this->getFilteredProducts(),
            'shippingProviderTypes' => ShippingProviderType::where('created_by', createdBy())
                ->where('status', 'active')
                ->select('id', 'name')->get(),
            'users' => $users,
            'filters' => $request->all(['search', 'status', 'account_id', 'sales_order_id', 'assigned_to', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    public function show($deliveryOrderId)
    {
        $deliveryOrder = DeliveryOrder::where('id', $deliveryOrderId)
            ->where('created_by', createdBy())
            ->with([
                'salesOrder',
                'account',
                'contact',
                'shippingProviderType',
                'creator',
                'assignedUser',
                'products'
            ])
            ->first();

        if (!$deliveryOrder) {
            return redirect()->route('delivery-orders.index')->with('error', __('Delivery order not found.'));
        }

        return Inertia::render('delivery-orders/show', [
            'deliveryOrder' => $deliveryOrder,
        ]);
    }

    public function create()
    {
        $accounts = Account::where('created_by', createdBy())
            ->where('status', 'active')
            ->when(auth()->user()->type !== 'company', function($q) {
                $q->where('assigned_to', auth()->id());
            })
            ->select('id', 'name')->get();
        $contacts = Contact::where('created_by', createdBy())
            ->where('status', 'active')
            ->when(auth()->user()->type !== 'company', function($q) {
                $q->where('assigned_to', auth()->id());
            })
            ->select('id', 'name')->get();
        $salesOrders = SalesOrder::where('created_by', createdBy())
            ->when(auth()->user()->type !== 'company', function($q) {
                $q->where('assigned_to', auth()->id());
            })
            ->select('id', 'name', 'order_number')->get();
        $products = $this->getFilteredProducts();
        $shippingProviderTypes = ShippingProviderType::where('created_by', createdBy())
            ->where('status', 'active')
            ->select('id', 'name')->get();

        $users = [];
        if (auth()->user()->type === 'company') {
            $users = \App\Models\User::where('created_by', createdBy())
                ->select('id', 'name', 'email')
                ->get();
        }

        return Inertia::render('delivery-orders/create', [
            'accounts' => $accounts,
            'contacts' => $contacts,
            'salesOrders' => $salesOrders,
            'products' => $products,
            'shippingProviderTypes' => $shippingProviderTypes,
            'users' => $users
        ]);
    }

    public function edit($id)
    {
        $deliveryOrder = DeliveryOrder::with([
            'salesOrder',
            'account',
            'contact',
            'shippingProviderType',
            'creator',
            'assignedUser',
            'products'
        ])
        ->where('created_by', createdBy())
        ->findOrFail($id);

        $accounts = Account::where('created_by', createdBy())
            ->where('status', 'active')
            ->when(auth()->user()->type !== 'company', function($q) {
                $q->where('assigned_to', auth()->id());
            })
            ->select('id', 'name')->get();
        $contacts = Contact::where('created_by', createdBy())
            ->where('status', 'active')
            ->when(auth()->user()->type !== 'company', function($q) {
                $q->where('assigned_to', auth()->id());
            })
            ->select('id', 'name')->get();
        $salesOrders = SalesOrder::where('created_by', createdBy())
            ->when(auth()->user()->type !== 'company', function($q) {
                $q->where('assigned_to', auth()->id());
            })
            ->select('id', 'name', 'order_number')->get();
        $products = $this->getFilteredProducts();
        $shippingProviderTypes = ShippingProviderType::where('created_by', createdBy())
            ->where('status', 'active')
            ->select('id', 'name')->get();

        $users = [];
        if (auth()->user()->type === 'company') {
            $users = \App\Models\User::where('created_by', createdBy())
                ->select('id', 'name', 'email')
                ->get();
        }

        return Inertia::render('delivery-orders/edit', [
            'deliveryOrder' => $deliveryOrder,
            'accounts' => $accounts,
            'contacts' => $contacts,
            'salesOrders' => $salesOrders,
            'products' => $products,
            'shippingProviderTypes' => $shippingProviderTypes,
            'users' => $users
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sales_order_id' => 'nullable|exists:sales_orders,id',
            'account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(function ($query) {
                    $query->where('created_by', createdBy())
                        ->where('status', 'active')
                        ->when(auth()->user()->type !== 'company', function ($q) {
                            $q->where('assigned_to', auth()->id());
                        });
                }),
            ],
            'contact_id' => [
                'nullable',
                Rule::exists('contacts', 'id')->where(function ($query) {
                    $query->where('created_by', createdBy())
                        ->where('status', 'active')
                        ->when(auth()->user()->type !== 'company', function ($q) {
                            $q->where('assigned_to', auth()->id());
                        });
                }),
            ],
            'shipping_provider_type_id' => [
                'nullable',
                Rule::exists('shipping_provider_types', 'id')->where(function ($query) {
                    $query->where('created_by', createdBy())
                        ->where('status', 'active');
                }),
            ],
            'delivery_address' => 'nullable|string',
            'delivery_city' => 'nullable|string',
            'delivery_state' => 'nullable|string',
            'delivery_postal_code' => 'nullable|string',
            'delivery_country' => 'nullable|string',
            'delivery_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after:delivery_date',
            'status' => 'nullable|in:pending,in_transit,delivered,cancelled',
            'tracking_number' => 'nullable|string',
            'delivery_notes' => 'nullable|string',
            'shipping_cost' => 'nullable|numeric|min:0',
            'assigned_to' => 'nullable|exists:users,id',
            'products' => 'nullable|array',
            'products.*.product_id' => [
                'required',
                Rule::exists('products', 'id')->where(function ($query) {
                    $query->where('created_by', createdBy())
                        ->where('status', 'active')
                        ->when(auth()->user()->type !== 'company', function ($q) {
                            $q->where('assigned_to', auth()->id());
                        });
                }),
            ],
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.unit_weight' => 'nullable|numeric|min:0',
        ]);

        $validated['created_by'] = createdBy();
        $validated['status'] = $validated['status'] ?? 'pending';
        
        if (auth()->user()->type !== 'company') {
            $validated['assigned_to'] = auth()->id();
        }

        $products = $validated['products'] ?? [];
        unset($validated['products']);

        $deliveryOrder = DeliveryOrder::create($validated);

        if (!empty($products)) {
            $syncData = [];
            foreach ($products as $product) {
                $productId = $product['product_id'];
                $unitWeight = $product['unit_weight'] ?? 0;
                $totalWeight = $product['quantity'] * $unitWeight;
                
                $syncData[$productId] = [
                    'quantity' => $product['quantity'],
                    'unit_weight' => $unitWeight,
                    'total_weight' => $totalWeight,
                ];
            }
            $deliveryOrder->products()->sync($syncData);
        }
        
        $deliveryOrder->calculateTotalWeight();

        return redirect()->back()->with('success', __('Delivery order created successfully.'));
    }

    public function update(Request $request, $deliveryOrderId)
    {
        $deliveryOrder = DeliveryOrder::where('id', $deliveryOrderId)
            ->where('created_by', createdBy())
            ->first();

        if (!$deliveryOrder) {
            return redirect()->back()->with('error', __('Delivery order not found.'));
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sales_order_id' => 'nullable|exists:sales_orders,id',
            'account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(function ($query) {
                    $query->where('created_by', createdBy())
                        ->where('status', 'active')
                        ->when(auth()->user()->type !== 'company', function ($q) {
                            $q->where('assigned_to', auth()->id());
                        });
                }),
            ],
            'contact_id' => [
                'nullable',
                Rule::exists('contacts', 'id')->where(function ($query) {
                    $query->where('created_by', createdBy())
                        ->where('status', 'active')
                        ->when(auth()->user()->type !== 'company', function ($q) {
                            $q->where('assigned_to', auth()->id());
                        });
                }),
            ],
            'shipping_provider_type_id' => [
                'nullable',
                Rule::exists('shipping_provider_types', 'id')->where(function ($query) {
                    $query->where('created_by', createdBy())
                        ->where('status', 'active');
                }),
            ],
            'delivery_address' => 'nullable|string',
            'delivery_city' => 'nullable|string',
            'delivery_state' => 'nullable|string',
            'delivery_postal_code' => 'nullable|string',
            'delivery_country' => 'nullable|string',
            'delivery_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after:delivery_date',
            'status' => 'nullable|in:pending,in_transit,delivered,cancelled',
            'tracking_number' => 'nullable|string',
            'delivery_notes' => 'nullable|string',
            'shipping_cost' => 'nullable|numeric|min:0',
            'assigned_to' => 'nullable|exists:users,id',
            'products' => 'nullable|array',
            'products.*.product_id' => [
                'required',
                Rule::exists('products', 'id')->where(function ($query) {
                    $query->where('created_by', createdBy())
                        ->where('status', 'active')
                        ->when(auth()->user()->type !== 'company', function ($q) {
                            $q->where('assigned_to', auth()->id());
                        });
                }),
            ],
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.unit_weight' => 'nullable|numeric|min:0',
        ]);

        if (auth()->user()->type !== 'company') {
            $validated['assigned_to'] = auth()->id();
        }

        $products = $validated['products'] ?? [];
        unset($validated['products']);
        
        $deliveryOrder->update($validated);

        if (!empty($products)) {
            $syncData = [];
            foreach ($products as $product) {
                $productId = $product['product_id'];
                $unitWeight = $product['unit_weight'] ?? 0;
                $totalWeight = $product['quantity'] * $unitWeight;
                
                $syncData[$productId] = [
                    'quantity' => $product['quantity'],
                    'unit_weight' => $unitWeight,
                    'total_weight' => $totalWeight,
                ];
            }
            $deliveryOrder->products()->sync($syncData);
        } else {
            $deliveryOrder->products()->detach();
        }
        
        $deliveryOrder->calculateTotalWeight();

        return redirect()->back()->with('success', __('Delivery order updated successfully.'));
    }

    public function destroy($deliveryOrderId)
    {
        $deliveryOrder = DeliveryOrder::where('id', $deliveryOrderId)
            ->where('created_by', createdBy())
            ->first();

        if (!$deliveryOrder) {
            return redirect()->back()->with('error', __('Delivery order not found.'));
        }

        $deliveryOrder->products()->detach();
        $deliveryOrder->delete();

        return redirect()->back()->with('success', __('Delivery order deleted successfully.'));
    }

    public function toggleStatus($deliveryOrderId)
    {
        $deliveryOrder = DeliveryOrder::where('id', $deliveryOrderId)
            ->where('created_by', createdBy())
            ->first();

        if (!$deliveryOrder) {
            return redirect()->back()->with('error', __('Delivery order not found.'));
        }

        $statusMap = [
            'pending' => 'in_transit',
            'in_transit' => 'delivered',
            'delivered' => 'pending',
            'cancelled' => 'pending'
        ];

        $newStatus = $statusMap[$deliveryOrder->status] ?? 'pending';
        $deliveryOrder->update(['status' => $newStatus]);

        return redirect()->back()->with('success', __('Delivery order status updated successfully.'));
    }

    public function assignUser(Request $request, $deliveryOrderId)
    {
        $deliveryOrder = DeliveryOrder::where('id', $deliveryOrderId)
            ->where('created_by', createdBy())
            ->first();

        if (!$deliveryOrder) {
            return redirect()->back()->with('error', __('Delivery order not found.'));
        }

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id'
        ]);

        $deliveryOrder->update(['assigned_to' => $validated['assigned_to']]);

        return redirect()->back()->with('success', __('User assigned to delivery order successfully.'));
    }

    private function getFilteredProducts()
    {
        if (auth()->user()->type === 'company') {
            return Product::where('created_by', createdBy())
                ->where('status', 'active')
                ->select('id', 'name')
                ->get();
        } else {
            return Product::where('created_by', createdBy())
                ->where('status', 'active')
                ->where('assigned_to', auth()->id())
                ->select('id', 'name')
                ->get();
        }
    }
}