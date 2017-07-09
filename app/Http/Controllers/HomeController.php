<?php

namespace BikeShare\Http\Controllers;

use BikeShare\Domain\Rent\RentsRepository;
use BikeShare\Domain\Stand\StandService;
use BikeShare\Domain\Stand\StandsRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Ivory\GoogleMap\Base\Coordinate;
use Ivory\GoogleMap\Base\Point;
use Ivory\GoogleMap\Base\Size;
use Ivory\GoogleMap\Control\ControlPosition;
use Ivory\GoogleMap\Control\FullscreenControl;
use Ivory\GoogleMap\Control\StreetViewControl;
use Ivory\GoogleMap\Control\ZoomControl;
use Ivory\GoogleMap\Control\ZoomControlStyle;
use Ivory\GoogleMap\Helper\Builder\ApiHelperBuilder;
use Ivory\GoogleMap\Helper\Builder\MapHelperBuilder;
use Ivory\GoogleMap\Map;
use Ivory\GoogleMap\Overlay\Animation;
use Ivory\GoogleMap\Overlay\Icon;
use Ivory\GoogleMap\Overlay\InfoWindow;
use Ivory\GoogleMap\Overlay\InfoWindowType;
use Ivory\GoogleMap\Overlay\Marker;
use Ivory\GoogleMap\Overlay\MarkerShape;
use Ivory\GoogleMap\Overlay\MarkerShapeType;
use Ivory\GoogleMap\Overlay\Symbol;
use Ivory\GoogleMap\Overlay\SymbolPath;

class HomeController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {

    }


    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // TODO where is bike?, where is not service stand
        $stands = app(StandsRepository::class)->with(['bikes'])->all();
        //$rents = auth()->user()->rents()->get();

        //$map = new Map();
        //$map->setAutoZoom(true);
        //$map->setStylesheetOption('width', "100%");
        //$map->setStylesheetOption('height', "550px");
        //$streetViewControl = new StreetViewControl(ControlPosition::TOP_LEFT);
        //$map->getControlManager()->setStreetViewControl($streetViewControl);
        //
        //$zoomControl = new ZoomControl(
        //    ControlPosition::TOP_LEFT,
        //    ZoomControlStyle::DEFAULT_
        //);
        //$map->getControlManager()->setZoomControl($zoomControl);
        //
        //foreach ($stands as $stand) {
        //    $icon = new Icon(
        //        StandService::chooseIcon($stand),
        //        new Point(70, 70),
        //        new Point(0, 0),
        //        new Size(60, 60)
        //    );
        //    $marker = new Marker(
        //        new Coordinate($stand->latitude, $stand->longitude),
        //        Animation::DROP,
        //        $icon,
        //        new Symbol(SymbolPath::CIRCLE),
        //        null,
        //        //new MarkerShape(MarkerShapeType::POLY, [1.1, 2.1, 1.4, 2.4]),
        //        [
        //            'clickable' => true,
        //            'title' => 'Title',
        //            'snippet' => 'Snippet',
        //            'label' => 'aalee',
        //            'labelAnchor' => new Point(2, 2),
        //            'pixelOffset' => new Size(2, 2),
        //        ]
        //    );

        //    $bikeCount = $stand->bikes->count();
        //    $html = "<div><p>$bikeCount</p><p>$stand->name</p></div>";
        //    //$marker->setVariable('html');
        //    $infoWindow = new InfoWindow($html);
        //    $infoWindow->setOpen(true);
        //    $marker->setInfoWindow($infoWindow);
        //    $map->getOverlayManager()->addMarker($marker);
        //
        //
        //}

        //dd($map);

        //$mapHelper = MapHelperBuilder::create()->build();
        //$apiHelper = ApiHelperBuilder::create()->setKey('AIzaSyCKqLy2d6OBXzMHLmzlpOPE3Ei9KOKvRIQ')->build();

        return view('home', [
            //'map' => $mapHelper->render($map),
            //'apiMap' => $apiHelper->render([$map]),
            'date' => Carbon::now()->toDateString(),
            'version' => config('app.version'),
            'stands' => $stands,
            //'activeRents' => $rents,
        ]);
    }
}
