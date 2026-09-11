<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Intl\Countries;

class NameController extends Controller
{
    public function countries()
    {
        $countries = [];
        foreach (glob(base_path('data/names_by_country/*_first_names.json')) as $path) {
            $code = explode('_', basename($path))[0];
            $countries[] = ['code' => $code, 'name' => Countries::exists($code) ? Countries::getName($code, 'en') : $code];
        }
        usort($countries, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $countries;
    }

    private function names(string $country, string $kind): array
    {
        $path = base_path("data/names_by_country/{$country}_{$kind}_names.json");
        abort_unless(is_file($path), 422, 'No name dataset exists for that country.');

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)['names'];
    }

    public function index(Request $request)
    {
        $data = $request->validate(['country' => 'required|regex:/^[A-Z]{2}$/', 'last_country' => 'nullable|regex:/^[A-Z]{2}$/', 'gender' => 'nullable|in:Male,Female',
            'q' => 'nullable|string|max:100', 'random' => 'nullable|boolean', 'count' => 'nullable|integer|min:1|max:50']);
        $first = array_values(array_filter($this->names($data['country'], 'first'), fn ($n) => empty($data['gender']) || ($n['gender'] ?? '') === $data['gender']));
        $last = $this->names($data['last_country'] ?? $data['country'], 'last');
        if ($request->boolean('random')) {
            abort_if(! $first || ! $last, 422, 'No names match these filters.');
            $results = [];
            $count = min($data['count'] ?? 10, count($first) * count($last));
            for ($i = 0; count($results) < $count && $i < $count * 20; $i++) {
                $firstName = $first[random_int(0, count($first) - 1)]['name'];
                $lastName = $last[random_int(0, count($last) - 1)]['name'];
                $name = $firstName.' '.$lastName;
                $results[$name] = ['name' => $name, 'first_name' => $firstName, 'last_name' => $lastName];
            }

            return ['results' => array_values($results)];
        }
        $filter = fn ($rows) => array_slice(array_values(array_filter($rows, fn ($n) => mb_stripos($n['name'], $data['q'] ?? '') !== false)), 0, 100);
        usort($first, fn ($a, $b) => ($a['rank'] ?? 99999) <=> ($b['rank'] ?? 99999));
        usort($last, fn ($a, $b) => ($a['rank'] ?? 99999) <=> ($b['rank'] ?? 99999));

        return ['first' => $filter($first), 'last' => $filter($last)];
    }
}
