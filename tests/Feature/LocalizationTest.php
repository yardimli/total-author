<?php
namespace Tests\Feature;
use App\Models\Book;
use App\Models\User;
use App\Services\Manuscript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class LocalizationTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.allow_language_change' => true]);
    }

    public function test_disabled_language_switch_forces_default_and_rejects_changes(): void
    {
        config(['app.allow_language_change' => false, 'app.default_locale' => 'tr']);
        $this->withSession(['locale' => 'en'])->get('/')->assertOk()->assertSee('<html lang="tr">', false)->assertDontSee('id="language-choice"', false);
        $this->post('/language', ['locale' => 'en'])->assertForbidden();
        $user = User::factory()->create();
        $user->locale = 'en';
        $user->save();
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('lang="tr"', false)->assertDontSee('id="language-choice"', false);
        $this->post('/language', ['locale' => 'en'])->assertForbidden();
        $this->assertSame('en', $user->fresh()->locale);
        config(['app.allow_language_change' => true]);
        $this->get('/dashboard')->assertOk()->assertSee('lang="en"', false)->assertSee('id="language-choice"', false);
    }

    public function test_configured_default_applies_only_without_a_language_preference(): void
    {
        config(['app.default_locale' => 'tr']);
        $this->get('/')->assertOk()->assertSee('<html lang="tr">', false);
        $this->withSession(['locale' => 'en'])->get('/')->assertOk()->assertSee('<html lang="en">', false);
        $this->flushSession();
        $user = User::factory()->create();
        $user->locale = 'en';
        $user->save();
        $this->actingAs($user)->get('/')->assertOk()->assertSee('<html lang="en">', false);
        $user->locale = null;
        $user->save();
        $this->get('/')->assertOk()->assertSee('<html lang="tr">', false);
        config(['app.default_locale' => 'invalid']);
        $this->get('/')->assertOk()->assertSee('<html lang="en">', false);
    }
    public function test_guest_language_switch_translates_public_and_login_pages(): void
    {
        $this->get('/')->assertOk()->assertSee('<html lang="en">', false);
        $this->from('/')->post('/language', ['locale'=>'tr'])->assertRedirect('/')->assertSessionHas('locale','tr')->assertCookie('locale','tr');
        $this->get('/')->assertOk()->assertSee('<html lang="tr">',false)->assertSee('Sözcüklerinize bir yer.')->assertSee('Giriş yap');
        $this->get('/login')->assertOk()->assertSee('Tekrar hoş geldiniz.')->assertSee('E-posta adresi');
        $this->get('/privacy')->assertOk()->assertSee('Gizlilik politikası');
        $this->get('/terms')->assertOk()->assertSee('Kullanım koşulları');
        $this->postJson('/login', ['email' => "test@example.com\n", 'password' => 'test'])->assertUnprocessable()->assertJsonPath('errors.email.0', 'Kontrol karakteri içermeyen bir e-posta adresi girin.');
        $this->post('/language',['locale'=>'invalid'])->assertSessionHasErrors('locale');
    }
    public function test_account_preference_applies_to_blade_and_json_errors_without_changing_data_values(): void
    {
        Http::fake();
        $user=User::factory()->create();
        $book=Book::create(['user_id'=>$user->id,'title'=>'Original title','document'=>Manuscript::fromText('Original words'),'codex_types'=>['People','Places']]);
        $this->actingAs($user)->post('/language',['locale'=>'tr'])->assertRedirect();
        $this->assertSame('tr',$user->fresh()->locale);
        $this->flushSession();
        $this->get('/books/'.$book->id)->assertOk()->assertSee('Kodeks')->assertSee('value="Male"',false)->assertSee('Tipografi ayarları');
        $this->postJson('/books',[])->assertUnprocessable()->assertJsonPath('errors.title.0','Başlık alanı zorunludur.');
        $this->patchJson('/api/books/'.$book->id,['revision'=>999,'document'=>Manuscript::fromText('Changed')])->assertStatus(409)->assertJsonPath('message',__('This book changed in another tab or operation. Reload the latest version before saving. Your local draft is preserved.'));
        $this->assertSame('Original words',Manuscript::text($book->fresh()->document));
        $this->assertSame(['People','Places'],$book->fresh()->codex_types);
        $this->post('/language',['locale'=>'en']);
        $this->get('/dashboard')->assertOk()->assertSee('A room for your stories.');
        $this->postJson('/books',[])->assertUnprocessable()->assertJsonPath('errors.title.0','The title field is required.');
    }
    public function test_catalogs_have_matching_keys_and_placeholders(): void
    {
        $en=json_decode(file_get_contents(lang_path('en.json')),true,512,JSON_THROW_ON_ERROR);
        $tr=json_decode(file_get_contents(lang_path('tr.json')),true,512,JSON_THROW_ON_ERROR);
        $this->assertSame(array_keys($en),array_keys($tr));
        foreach($en as $key=>$text){
            preg_match_all('/:([a-zA-Z_][a-zA-Z_0-9]*)/',$text,$a);
            preg_match_all('/:([a-zA-Z_][a-zA-Z_0-9]*)/',$tr[$key],$b);
            sort($a[0]);sort($b[0]);$this->assertSame($a[0],$b[0],$key);
        }
    }
}
