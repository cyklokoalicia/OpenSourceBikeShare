<?php
namespace BikeShare\Domain\Note;

use BikeShare\Domain\Core\Repository;

class NotesRepository extends Repository
{

    public function model()
    {
        return Note::class;
    }
}
