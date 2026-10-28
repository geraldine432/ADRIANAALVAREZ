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
            'created_at' => $sale->created_at?->toDateString(),
            'details' => $sale->details->map(fn ($detail) => [
                'product' => $detail->product?->name,
                'quantity' => $detail->quantity,
                'subtotal' => $detail->subtotal,
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

            $totalInCents = $items->sum(fn ($item) => (int) round((float) $products[$item['product_id']]->price * 100) * $item['quantity']);
            $total = $totalInCents / 100;
            $sale = Sale::create(['user_id' => $validated['user_id'], 'total' => $total]);

            foreach ($items as $item) {
                $product = $products[$item['product_id']];
                $subtotal = ((int) round((float) $product->price * 100) * $item['quantity']) / 100;
                $sale->details()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'subtotal' => $subtotal,
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
