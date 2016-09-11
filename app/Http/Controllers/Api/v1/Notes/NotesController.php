<?php
namespace BikeShare\Http\Controllers\Api\v1\Notes;

use BikeShare\Domain\Note\NotesRepository;
use BikeShare\Domain\Note\NoteTransformer;
use BikeShare\Http\Controllers\Api\v1\Controller;
use Illuminate\Http\Request;

class NotesController extends Controller
{
    protected $noteRepo;

    public function __construct(NotesRepository $notesRepository)
    {
        $this->noteRepo = $notesRepository;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $notes = $this->noteRepo->all();

        return $this->response->collection($notes, new NoteTransformer());
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

    /**
     * Display the specified resource.
     *
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function show($uuid)
    {
        if (! $note = $this->noteRepo->findByUuid($uuid)) {
            return $this->response->errorNotFound('Note not found');
        }

        return $this->response->item($note, new NoteTransformer());
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $uuid)
    {
        if (! $note = $this->noteRepo->findByUuid($uuid)) {
            return $this->response->errorNotFound('Note not found');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function destroy($uuid)
    {
        if (! $note = $this->noteRepo->findByUuid($uuid)) {
            return $this->response->errorNotFound('Note not found');
        }

        $bikeNum = $note->bike;
        $note->delete();

        return response('Note for bike ' . $bikeNum . ' deleted!');
    }
}
