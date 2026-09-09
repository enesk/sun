{{-- Infografik aus den Key-Facts (#16/#71).

     Eigenstaendiges Bild mit eigenem Alt-Text, nicht als Hintergrund: nur so
     ist sie ohne Bild beschreibbar. Sie steht unterhalb der Faltung, deshalb
     lazy. Die Geometrie ist fest reserviert (16:9), damit nichts springt. --}}
@if(!empty($infographic))
    <figure class="ratgeber-infographic">
        <img src="{{ $infographic['url'] }}"
             alt="{{ $infographic['alt'] }}"
             width="{{ (int) config('content.assets.infographic.width', 1200) }}"
             height="{{ (int) config('content.assets.infographic.height', 675) }}"
             loading="lazy"
             decoding="async"
             class="ratgeber-infographic__image">
    </figure>
@endif
