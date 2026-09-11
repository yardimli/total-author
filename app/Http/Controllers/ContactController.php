<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validateWithBag('contact', ['name' => 'required|string|max:200', 'email' => 'required|email|max:255', 'subject' => 'required|string|max:200', 'message' => 'required|string|max:10000']);
        DB::table('contact_messages')->insert($data + ['created_at' => now(), 'updated_at' => now()]);

        return back()->with('contact_status', 'Thank you. Your message has been saved for our team.');
    }
}
