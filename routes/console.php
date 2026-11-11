<?php
use App\Models\Puzzle;
use App\Models\User;
use App\Services\PuzzleDocument;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

Artisan::command('garden:examples', function (PuzzleDocument $parser) {
    $author = User::firstOrCreate(['email'=>'studio-examples@missed-it.invalid'], ['name'=>'Studio examples','password'=>Str::random(64)]);
    foreach (glob(resource_path('examples/*.json')) as $file) {
        $raw=file_get_contents($file);
        $d=$parser->parse($raw);
        Puzzle::firstOrCreate(['user_id'=>$author->id,'checksum'=>hash('sha256',$raw)], [
            'slug'=>(string) Str::uuid(), 'title'=>$d['Title'], 'brief'=>$d['Brief'],
            'notes'=>'A starting template adapted from the game’s WorkshopExamples.cs. Try it in Puzzle Studio and make it your own. No clear replay is included.',
            'difficulty'=>$d['Title']==='Spring bank' ? 'gentle' : 'tricky',
            'document'=>$raw,'mechanics'=>$parser->mechanics($d),'has_replay'=>false,
        ]);
    }
    $this->info('Studio example templates are available. Existing examples were preserved.');
})->purpose('Add three optional Puzzle Studio example templates');
