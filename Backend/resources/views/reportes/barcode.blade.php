<center>
    <br>
    {!! '<img id="barcode" src="data:image/png;base64,' . DNS1D::getBarcodePNG($codigo, 'C39+', 2, 50, array(0,0,0), true) . '" alt="barcode"   />' !!}
    <br><br>
    <button onclick="descargarImagen()">Guardar imagen</button>
</center>

<br> <br> 
<center>
    <br>
    {!! '<img id="qrcode" src="data:image/png;base64,' . DNS2D::getBarcodePNG($codigo, 'QRCODE', 10, 10, array(0,0,0), true) . '" alt="barcode"   />' !!}
    <br><br>
    <button onclick="descargarImagen2()">Guardar imagen</button>
</center>

<script>
    function descargarImagen2() {
        // URL de la imagen que deseas descargar
        var urlImagen = document.getElementById('qrcode').getAttribute('src');

        // Crea un enlace temporal
        var enlaceTemporal = document.createElement('a');
        enlaceTemporal.href = urlImagen;
        enlaceTemporal.download = 'qrcode.jpg';

        // Simula el clic en el enlace para iniciar la descarga
        enlaceTemporal.click();
    }
</script>


<script>
    function descargarImagen() {
        // URL de la imagen que deseas descargar
        var urlImagen = document.getElementById('barcode').getAttribute('src');

        // Crea un enlace temporal
        var enlaceTemporal = document.createElement('a');
        enlaceTemporal.href = urlImagen;
        enlaceTemporal.download = 'barcode.jpg';

        // Simula el clic en el enlace para iniciar la descarga
        enlaceTemporal.click();
    }
</script>
