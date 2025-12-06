<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mail;
use App\Http\Requests\DemoPostRequest;

class HomeController extends Controller
{


    public function index()
    {
        return view('index');
    }

    public function demoPost(DemoPostRequest $request)
    {


        try {
            
            Mail::send('mails.demo', ['request' => $request], function ($m) use ($request) {
                $m->from('info@websis.me', 'Wanda')
                ->to('info@websis.me', 'Wanda')
                ->cc('alvarado.websis@gmail.com')
                ->replyTo($request->correo)
                ->subject('Demo Wanda');
            });

        } catch (Exception $e) {

            return Redirect::back();
            
        }

        return view('sections.demo');
    }

}
