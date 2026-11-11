@props(['board', 'compact' => false])
@php
    $height = $board['ArenaHeight'] ?? 700;
    $colors = ['Neutral'=>'#aeb79a','Gravity'=>'#4d9c91','Repulsor'=>'#d67d68','Accelerator'=>'#d9b250','Orbiter'=>'#8f94bf','Capture'=>'#8099be','Bumper'=>'#609fbd'];
    $launch = $board['LaunchOrigin'] ?? ['X'=>320,'Y'=>648];
@endphp
<svg class="board {{ $compact ? 'compact' : '' }}" viewBox="0 0 640 {{ $height }}" role="img" aria-label="Board preview for {{ $board['Title'] }}">
    <rect x="0" y="0" width="640" height="{{ $height }}" rx="28" fill="#e8eddf"/>
    <rect x="28" y="31" width="584" height="{{ $height-51 }}" rx="22" fill="#f5f5e9" stroke="#c9d1b8" stroke-width="3"/>
    @for($x=80;$x<610;$x+=80) @for($y=80;$y<$height-30;$y+=80)<circle cx="{{ $x }}" cy="{{ $y }}" r="2" fill="#d5dcc7"/>@endfor @endfor
    @foreach($board['Rivers'] ?? [] as $river)
        <polyline points="@foreach($river['Points'] as $p){{ $p['X'] }},{{ $p['Y'] }} @endforeach" fill="none" stroke="#8dc7cf" stroke-width="{{ $river['Width'] ?? 44 }}" stroke-linecap="round" stroke-linejoin="round" opacity=".65"/>
    @endforeach
    @foreach($board['Surfaces'] ?? [] as $surface)
        <line x1="{{ $surface['A']['X'] }}" y1="{{ $surface['A']['Y'] }}" x2="{{ $surface['B']['X'] }}" y2="{{ $surface['B']['Y'] }}" stroke="{{ ['Mirror'=>'#93a9c3','Water'=>'#8dc7cf','Breakable'=>'#af8c69'][$surface['Kind']] ?? '#7f8a6b' }}" stroke-width="{{ $surface['Thickness'] ?? 12 }}" stroke-linecap="round"/>
    @endforeach
    @foreach($board['Portals'] as $portal) @foreach(['A','B'] as $side)
        <circle cx="{{ $portal[$side]['Center']['X'] }}" cy="{{ $portal[$side]['Center']['Y'] }}" r="{{ $portal[$side]['Radius'] }}" fill="#eee4f5" stroke="#a48bb9" stroke-width="6"/>
    @endforeach @endforeach
    @foreach($board['Pegs'] as $peg)
        @php($color = $colors[$peg['Kind']] ?? '#aeb79a')
        @if(in_array($peg['Kind'], ['Gravity','Repulsor','Accelerator']))
            <circle cx="{{ $peg['Home']['X'] }}" cy="{{ $peg['Home']['Y'] }}" r="{{ $peg['Field'] }}" fill="{{ $color }}" fill-opacity=".09" stroke="{{ $color }}" stroke-opacity=".3" stroke-dasharray="5 9" stroke-width="2"/>
        @endif
        @if(in_array($peg['Kind'], ['Capture','Orbiter']))
            <circle cx="{{ $peg['Home']['X'] }}" cy="{{ $peg['Home']['Y'] }}" r="{{ $peg['OrbitRadius'] }}" fill="none" stroke="{{ $color }}" stroke-dasharray="5 6" stroke-width="3"/>
        @endif
        <circle cx="{{ $peg['Home']['X'] }}" cy="{{ $peg['Home']['Y']+4 }}" r="{{ $peg['Radius']+3 }}" fill="#344b3918"/>
        <circle cx="{{ $peg['Home']['X'] }}" cy="{{ $peg['Home']['Y'] }}" r="{{ $peg['Radius'] }}" fill="{{ $color }}" stroke="#fff9" stroke-width="4"/>
        <circle cx="{{ $peg['Home']['X']-4 }}" cy="{{ $peg['Home']['Y']-5 }}" r="4" fill="#fffc"/>
    @endforeach
    @foreach($board['LinkedTargets'] ?? [] as $target)
        <circle cx="{{ $target['Position']['X'] }}" cy="{{ $target['Position']['Y'] }}" r="{{ $target['Radius'] ?? 22 }}" fill="#ebc7c1" stroke="#b17d79" stroke-width="3"/>
    @endforeach
    <circle cx="{{ $board['Home']['Position']['X'] }}" cy="{{ $board['Home']['Position']['Y'] }}" r="{{ $board['Home']['Radius']+10 }}" fill="#d7b653" opacity=".13"/>
    <circle cx="{{ $board['Home']['Position']['X'] }}" cy="{{ $board['Home']['Position']['Y'] }}" r="{{ $board['Home']['Radius'] }}" fill="#e4c96d" stroke="#aa913f" stroke-width="3"/>
    <text x="{{ $board['Home']['Position']['X'] }}" y="{{ $board['Home']['Position']['Y']+5 }}" text-anchor="middle" font-size="14" font-weight="700" font-family="sans-serif" fill="#665726">HOME</text>
    <circle cx="{{ $launch['X'] }}" cy="{{ $launch['Y'] }}" r="19" fill="#304d3c"/>
    <path d="M {{ $launch['X']-5 }} {{ $launch['Y']-3 }} q 4 -22 18 -22 q 0 19 -18 22" fill="#8caa75"/>
    <circle cx="{{ $launch['X']-5 }}" cy="{{ $launch['Y']+2 }}" r="3" fill="#fff"/>
</svg>
