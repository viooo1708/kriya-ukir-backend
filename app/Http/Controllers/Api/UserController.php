<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;

class UserController extends Controller
{
    /**
     * Menampilkan seluruh pelanggan.
     */
    public function index()
    {
        $users = User::where('role', 'pelanggan')
            ->orderBy('nama')
            ->get();

        return response()->json([
            'data' => $users
        ]);
    }

    /**
     * Detail pelanggan.
     */
    public function show(User $user)
    {
        return response()->json([
            'data' => $user
        ]);
    }

    /**
     * Hapus pelanggan.
     */
    public function destroy(User $user)
    {
        $user->delete();

        return response()->json([
            'message' => 'Pelanggan berhasil dihapus.'
        ]);
    }
}
