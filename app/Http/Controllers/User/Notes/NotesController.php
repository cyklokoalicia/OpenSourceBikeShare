<?php
namespace BikeShare\Http\Controllers\User\Notes;

use BikeShare\Domain\Note\NotesRepository;
use BikeShare\Domain\Note\NoteTransformer;
use BikeShare\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotesController extends Controller
{
    protected $noteRepo;

    public function __construct(NotesRepository $notesRepository)
    {
        $this->noteRepo = $notesRepository;
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }
}
