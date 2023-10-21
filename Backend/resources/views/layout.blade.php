<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />

    <title>Wlogic - Software de gestión para empresas de transporte.</title>

    <meta name='viewport'content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0'/>

    <meta name="description"        content="Ten el control de tu empresa sin importar donde estés, monitorea y automatiza los procesos de facturación y la gestión de inventarios, compras, clientes, proveedores y más.">
    <meta name="keywords"           content="sistema, software, software gestion, empresas de transporte, sistema en linea, sistema para empresas de transporte, sistema comandas, el salvador">
    <meta name="author"             content="Jesus Alvarado">

    <meta property="og:url"         content="http://wanda.websis.me" />
    <meta property="og:type"        content="product"/>
    <meta property="og:title"       content="Wgas - Software de gestión para empresas de transporte." />
    <meta property="og:description" content="Ten el control de tu empresa sin importar donde estés, monitorea y automatiza los procesos de facturación y la gestión de inventarios, compras, clientes, proveedores y más." />
    <meta property="og:image"       content="{{ asset('img/logo.png') }}" />

    <link rel="shortcut icon" type="image/x-icon" href="/favicon.ico">
    <link rel="alternate" hreflang="es-SV" href="http://websis.me/" />

    <!-- CSS Files -->
        <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700|Roboto+Slab:400,700|Material+Icons" />
        <script src="https://kit.fontawesome.com/432b1ad5f3.js" crossorigin="anonymous"></script>
        <link href="{{ asset('css/material-kit.css') }}" rel="stylesheet"/>
        <link href="{{ asset('css/demo.css') }}" rel="stylesheet" />
        <link href="{{ asset('css/estilo.css') }}" rel="stylesheet" />
    
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-JJM8FYZKTL"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-JJM8FYZKTL');
    </script>

</head>

<body class="index-page">
    
    <div itemscope itemtype="https://schema.org/Product">
        @yield('content')
    </div>
    
    <!--   Core JS Files   -->
    <script src="{{ asset('js/jquery.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/bootstrap.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/material.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/nouislider.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/material-kit.js') }}" type="text/javascript"></script>

    <script type="text/javascript">

        $(document).ready(function() {
            if ($('.slider').length != 0) {
              // Sliders Init
              materialKit.initSliders();
            }
        });

        function scrollToDownload() {
          if ($('.planes').length != 0) {
            $("html, body").animate({
              scrollTop: $('.planes').offset().top
            }, 1000);
          }
        }
    </script>

</body>
</html>
