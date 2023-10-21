<nav class="navbar navbar-color-on-scroll fixed-top navbar-expand-lg navbar-transparent" color-on-scroll="100" id="sectionsNav">
    <div class="container">
      <div class="navbar-translate">
        <a class="navbar-brand" href="{{ route('home') }}">
          
          <div class="logo">
              <img itemprop="image" src="{{asset('img/logo.png')}}" width="30px" alt="Logo Wgas" rel="tooltip" title="<b>Wgas</b> es diseñado y desarrollado por el equipo de <b>Websis</b>" data-placement="bottom" data-html="true">
              Wlogic
          </div>
      	</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" aria-expanded="false" aria-label="Toggle navigation">
          <span class="sr-only">Toggle navigation</span>
          <span class="navbar-toggler-icon"></span>
          <span class="navbar-toggler-icon"></span>
          <span class="navbar-toggler-icon"></span>
        </button>
      </div>
      <div class="collapse navbar-collapse">
        <ul class="navbar-nav ml-auto">
          <li class="nav-item">
            <a class="nav-link" href="{{ asset('docs/manual.pdf') }}" target="_blank" rel="tooltip" title="Manual de Usuario" data-placement="bottom">
              <i class="material-icons">bookmark</i> Manual
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="javascript:void(0)" onclick="scrollToDownload()">
              <i class="material-icons">attach_money</i> Precios
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="{{ route('registro') }}" rel="tooltip" title="Crea una cuenta gratuita y pruebalo" data-placement="bottom">
              <i class="material-icons">unarchive</i> Pruébalo gratis
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" rel="tooltip" title="" data-placement="bottom" href="https://twitter.com/AgenciaWebsis" target="_blank" data-original-title="Síguenos en Twitter">
              <i class="fa fa-twitter"></i>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" rel="tooltip" title="" data-placement="bottom" href="https://www.facebook.com/AgenciaWebsiss" target="_blank" data-original-title="Síguenos en Facebook">
              <i class="fa fa-facebook-square"></i>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" rel="tooltip" title="" data-placement="bottom" href="https://www.instagram.com/AgenciaWebsis" target="_blank" data-original-title="Síguenos en Instagram">
              <i class="fa fa-instagram"></i>
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>
