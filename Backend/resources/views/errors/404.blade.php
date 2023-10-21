<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>No encontrado</title>

<style>
    html{
        margin: 0; padding: 0; width: 100%; height: 100%; font-family: 'ubuntu', 'arial';
    }
    section{
        text-align: center; margin: 70px;        
    }
    hr{ 
        border: 0.5px solid #D1D0D0; width: 70%;
    }
    h1{
        font-size: 3em; font-weight: 100; color: #f9b54c;
    }
    p{
        font-size: 1em; font-weight: 100; color: #bbb;
    }
    a{
        font-size: 1.5em;
        color: #ffd05b;
        text-decoration: none;
    }
</style>

</head>
<body>
    
    <section>
        <img src="{{ asset('img/logo.png') }}" width="70px" alt="logo Wgas">
        <h1>No encontrado 404</h1>
        <hr>
        <p><a href="{{ route('home') }}">Volver a Wgas</a></p>
    </section>
    
</body>
</html>