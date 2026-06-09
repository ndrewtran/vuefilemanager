<?php
namespace Domain\Teams\DTO;

class CreateTeamFolderData
{
    public string $name;
    public array $invitations;

    public function __construct(array $data)
    {
        $this->name = $data['name'];
        $this->invitations = $data['invitations'];
    }

    public static function fromRequest($request): self
    {
        return new self([
            'name'        => $request->input('name'),
            'invitations' => $request->input('invitations'),
        ]);
    }
}
