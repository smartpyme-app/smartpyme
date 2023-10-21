<div class="mb-5 pb-5" id="carousel">
      <div class="container">
        <div class="row">

        	<div class="col-md-12 text-center">
        	    <h2>Galería de imágenes</h2>
        	</div>

          <div class="col-md-10 mr-auto ml-auto">
           
           <div id="carouselExampleIndicators" class="carousel slide" data-ride="carousel">
             <ol class="carousel-indicators">
             	@for ($i = 1; $i < 11; $i++)
	               <li data-target="#carouselExampleIndicators" data-slide-to="{{ $i - 1 }}" class="{{ $i == 1 ? 'active' : '' }}"></li>
	            @endfor
             </ol>
             <div class="carousel-inner">
             	@for ($i = 1; $i < 11; $i++)
	               <div class="carousel-item {{ $i == 1 ? 'active' : '' }}">
	                 <img class="d-block w-100" src="{{ asset('/img/galeria/' . $i . '.JPG') }}" alt="First slide">
	               </div>
             	@endfor
             </div>
             <a class="carousel-control-prev" href="#carouselExampleIndicators" role="button" data-slide="prev">
               <span class="carousel-control-prev-icon" aria-hidden="true"></span>
               <span class="sr-only">Previous</span>
             </a>
             <a class="carousel-control-next" href="#carouselExampleIndicators" role="button" data-slide="next">
               <span class="carousel-control-next-icon" aria-hidden="true"></span>
               <span class="sr-only">Next</span>
             </a>
           </div>

          </div>
        </div>
      </div>
    </div>