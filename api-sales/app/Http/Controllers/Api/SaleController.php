<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SaleController extends Controller
{
    public function index()
    {
        $sales = Sale::with('details.product')->latest()->get()->map(fn (Sale $sale) => [
            'id' => $sale->id,
            'total' => $sale->total,
            'total_discount' => $sale->total_discount,
            'created_at' => $sale->created_at?->toDateString(),
            'details' => $sale->details->map(fn ($detail) => [
                'product' => $detail->product?->name,
                'quantity' => $detail->quantity,
                'subtotal' => $detail->subtotal,
                'discount_percent' => $detail->discount_percent,
                'discount_amount' => $detail->discount_amount,
                'subtotal_with_discount' => round(((int) round((float) $detail->subtotal * 100) - (int) round((float) $detail->discount_amount * 100)) / 100, 2),
            ])->values(),
        ]);

        return response()->json($sales);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'integer', 'in:1', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.discount_percent' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0', 'max:10'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Los datos enviados no son validos', 'errors' => $validator->errors()], 422);
        }
        $validated = $validator->validated();

        $sale = DB::transaction(function () use ($validated) {
            $items = collect($validated['items']);
            $products = Product::whereIn('id', $items->pluck('product_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);
                if (! $product || $product->stock < $item['quantity']) {
                    return ['error' => "Stock insuficiente para el producto {$item['product_id']}"];
                }
            }

            $calculatedItems = $items->map(function ($item) use ($products) {
                $subtotalCents = (int) round((float) $products[$item['product_id']]->price * 100) * $item['quantity'];
                $discountPercent = (float) ($item['discount_percent'] ?? 0);
                $discountCents = (int) round($subtotalCents * $discountPercent / 100);

                return [...$item, 'subtotal_cents' => $subtotalCents, 'discount_percent' => $discountPercent, 'discount_cents' => $discountCents];
            });
            $discountCents = $calculatedItems->sum('discount_cents');
            $totalCents = $calculatedItems->sum(fn ($item) => $item['subtotal_cents'] - $item['discount_cents']);
            $sale = Sale::create([
                'user_id' => $validated['user_id'],
                'total' => $totalCents / 100,
                'total_discount' => $discountCents / 100,
            ]);

            foreach ($calculatedItems as $item) {
                $product = $products[$item['product_id']];
                $sale->details()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'subtotal' => $item['subtotal_cents'] / 100,
                    'discount_percent' => $item['discount_percent'],
                    'discount_amount' => $item['discount_cents'] / 100,
                ]);
                $product->decrement('stock', $item['quantity']);
            }

            return $sale;
        });

        if (is_array($sale)) {
            return response()->json([
                'message' => 'Stock insuficiente',
                'errors' => ['items' => [$sale['error']]],
            ], 422);
        }

        return response()->json([
            'message' => 'Venta creada correctamente',
            'sale_id' => $sale->id,
            'total' => $sale->total,
            'total_discount' => $sale->total_discount,
        ], 201);
    }

    public function destroy(int $id)
    {
        $sale = Sale::with('details')->find($id);
        if (! $sale) {
            return response()->json(['message' => 'Venta no encontrada'], 404);
        }

        DB::transaction(function () use ($sale) {
            $details = $sale->details;
            $products = Product::whereIn('id', $details->pluck('product_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($details as $detail) {
                if ($product = $products->get($detail->product_id)) {
                    $product->increment('stock', $detail->quantity);
                }
            }
            $sale->details()->delete();
            $sale->delete();
        });

        return response()->json(['message' => 'Venta eliminada correctamente']);
    }
}
