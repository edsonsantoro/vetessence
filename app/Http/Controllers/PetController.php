<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Models\Tutor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PetController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:pets');
    }

    public function index(Request $request)
    {
        $query = Pet::with('tutors', 'breedRelation');

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%")
                  ->orWhere('breed', 'like', "%{$request->search}%");
        }

        if ($request->species) {
            $query->where('species', $request->species);
        }

        // AgroVerde: paginado. Sem isso a listagem carrega a tabela inteira
        // e estoura o memory_limit do PHP (erro 500). Com a base real do
        // SimplesVet são ~16 mil pets. A view continua funcionando porque
        // @foreach percorre Paginator igual percorre Collection.
        //
        // `appends()` e não `->withQueryString()`: este último é método da
        // Response, não da View — chamado na View ele cai no __call mágico e
        // morre com "Undefined array key 0".
        // Ver AGROVERDE.md §11 (camada C — edições mínimas no core).
        $pets = $query->orderBy('name')
            ->paginate($request->input('per_page', 50))
            ->appends($request->query());

        return view('pets.index', compact('pets'));
    }

    public function create()
    {
        $tutors = Tutor::with('user')->get()->sortBy(function($tutor) {
            return $tutor->user ? $tutor->user->name : '';
        });
        return view('pets.create', compact('tutors'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'species' => 'required|in:' . implode(',', array_keys(config('species'))),
            'breed' => 'nullable|string|max:100',
            'gender' => 'required|in:male,female',
            'birth_date' => 'nullable|date',
            'weight' => 'nullable|numeric|min:0',
            'color' => 'nullable|string|max:50',
            'microchip' => 'nullable|string|max:50',
            'size' => 'nullable|in:small,medium,large,giant',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            'tutor_id' => 'required|exists:tutors,id',
            'is_primary' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $pet = Pet::create($validated);
            
            if ($request->hasFile('photo')) {
                $pet->savePhoto($request->file('photo'), 'pets');
            }
            
            $pet->tutors()->attach($validated['tutor_id'], [
                'is_primary' => $request->boolean('is_primary', true),
                'relationship' => 'proprietário',
            ]);

            DB::commit();
            return redirect()->route('pets.index')->with('success', 'Pet cadastrado com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Erro ao cadastrar pet.')->withInput();
        }
    }

    public function show(Pet $pet)
    {
        $pet->load(['tutors', 'appointments', 'medicalRecords', 'vaccinations']);
        return view('pets.show', compact('pet'));
    }

    public function edit(Pet $pet)
    {
        $tutors = Tutor::with('user')->get()->sortBy(function($tutor) {
            return $tutor->user ? $tutor->user->name : '';
        });
        $petTutor = $pet->tutors()->first();
        return view('pets.edit', compact('pet', 'tutors', 'petTutor'));
    }

    public function update(Request $request, Pet $pet)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'species' => 'required|in:' . implode(',', array_keys(config('species'))),
            'breed' => 'nullable|string|max:100',
            'gender' => 'required|in:male,female',
            'birth_date' => 'nullable|date',
            'weight' => 'nullable|numeric|min:0',
            'color' => 'nullable|string|max:50',
            'microchip' => 'nullable|string|max:50',
            'size' => 'nullable|in:small,medium,large,giant',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        $pet->update($validated);

        if ($request->hasFile('photo')) {
            $pet->savePhoto($request->file('photo'), 'pets');
        }

        return redirect()->route('pets.index')->with('success', 'Pet atualizado com sucesso!');
    }

    public function destroy(Pet $pet)
    {
        if ($pet->appointments()->count() > 0) {
            return back()->with('error', 'Não é possível excluir pet com atendimentos.');
        }

        $pet->delete();

        return redirect()->route('pets.index')->with('success', 'Pet excluído com sucesso!');
    }
}
