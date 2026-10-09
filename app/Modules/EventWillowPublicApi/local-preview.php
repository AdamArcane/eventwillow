<?php
// Local DDEV only. Uses model events without notifications/network writes.
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!str_ends_with(parse_url(config('app.url'),PHP_URL_HOST) ?? '', '.ddev.site') || config('database.connections.mysql.host') !== 'db') throw new RuntimeException('Local DDEV only');
config(['services.google.backend_key'=>'','queue.default'=>'sync']);
\Illuminate\Support\Facades\DB::transaction(function () {
    $user = \App\Models\User::firstOrCreate(['email'=>'integration-preview@eventwillow.local'],['name'=>'Local preview organizer','password'=>bcrypt(bin2hex(random_bytes(20))),'email_verified_at'=>now(),'timezone'=>'America/New_York']);
    $role=\App\Models\Role::firstOrNew(['subdomain'=>'willow-studio']);
    $role->forceFill(['subdomain'=>'willow-studio','user_id'=>$user->id,'name'=>'Willow Studio','type'=>'venue','email'=>'integration-preview@eventwillow.local','email_verified_at'=>now(),'timezone'=>'America/New_York','city'=>'Brooklyn','state'=>'Connecticut','country_code'=>'US','address1'=>'15 Willow Lane','description'=>'A fictional channel used only in local integration previews.','hide_past_events'=>false,'is_deleted'=>false,'is_unlisted'=>false]);
    \App\Models\Role::withoutEvents(fn()=> $role->save());
    $role->users()->syncWithoutDetaching([$user->id=>['level'=>'owner']]);
    $names=['Autumn Gathering','Moonlit Workshop','Community Circle','Herbal Craft Evening','Seasonal Market','Quiet Morning Gathering'];
    foreach ($names as $i=>$name) {
        $event=\App\Models\Event::firstOrNew(['slug'=>'local-preview-'.\Illuminate\Support\Str::slug($name)]);
        $event->forceFill(['slug'=>'local-preview-'.\Illuminate\Support\Str::slug($name),'user_id'=>$user->id,'creator_role_id'=>$role->id,'name'=>$name,'description'=>'Fictional event for the local EventWillow WordPress integration preview. No registration or payment is available.','short_description'=>'A welcoming seasonal gathering. Local preview content only.','starts_at'=>now('America/New_York')->addDays($i+2)->setTime(18,0)->utc()->format('Y-m-d H:i:s'),'duration'=>2,'is_draft'=>false,'is_private'=>false,'is_internal'=>false,'is_cancelled'=>false,'is_hidden_from_discovery'=>false,'days_of_week'=>$i===2?'1111111':null,'recurring_frequency'=>$i===2?'daily':null,'recurring_exclude_dates'=>[]]);
        \App\Models\Event::withoutEvents(fn()=> $event->save());
        $event->roles()->syncWithoutDetaching([$role->id=>['is_accepted'=>true]]);
    }
    echo 'Local fictional preview channel and six events ready.'.PHP_EOL;
});
