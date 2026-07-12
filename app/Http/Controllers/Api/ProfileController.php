<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Menampilkan data profil user yang sedang login.
     */
    public function show(Request $request)
    {
        return response()->json(['data' => $request->user()]);
    }

    /**
     * Memperbarui data profil (nama, no_hp, alamat, password, foto).
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'nama' => 'sometimes|string|max:150',
            'no_hp' => 'nullable|string|max:20',
            'alamat' => 'nullable|string',
            'password' => 'nullable|string|min:8|confirmed',
            'foto' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['nama', 'no_hp', 'alamat']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('profile-photos', 'public');
            $data['foto'] = Storage::url($path);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'data' => $user->fresh(),
        ]);
    }
}
