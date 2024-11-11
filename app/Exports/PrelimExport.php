<?php

namespace App\Exports;

use App\Models\ClassCard;
use App\Models\Score;
use App\Models\Attendance;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PrelimExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithTitle
{
    protected $teacherId;
    protected $subjectId;

    public function __construct($teacherId, $subjectId = null)
    {
        $this->teacherId = $teacherId;
        $this->subjectId = $subjectId; // Save the subject ID for use in the export
    }

    public function collection()
    {
        $students = \DB::table('students')
            ->join('class_cards', 'students.id', '=', 'class_cards.student_id')
            ->join('sections', 'students.section_id', '=', 'sections.id')
            ->where('sections.user_id', $this->teacherId) // Ensure the section is associated with the teacher
            ->where('class_cards.subject_id', $this->subjectId)
            ->select(
                'students.id',
                'students.student_number',
                'students.first_name',
                'students.middle_name',
                'students.last_name',
                'students.course',
                'sections.name as section_name',
                'class_cards.id as class_card_id'
            )
            ->orderBy('sections.id', 'ASC')
            ->get();

        return $students->map(function ($student) {
            $classCard = ClassCard::find($student->class_card_id);
            $scores = $classCard 
                ? Score::where('class_card_id', $classCard->id)->get()->groupBy('term') 
                : collect(); // Return an empty collection if no class card found

            // Fetch raw scores for prelim
            $prelimScores = $scores->get('prelim', collect())->groupBy('type');

            // Prepare the raw scores array
            return [
                'Student Number' => $student->student_number,
                'First Name' => $student->first_name,
                'Middle Name' => $student->middle_name,
                'Last Name' => $student->last_name,
                'Course' => $student->course,
                'Section' => $student->section_name,
                'Subject' => $classCard ? $classCard->subject->name : 'N/A',
                'Prelim Performance Task Scores' => $prelimScores->get('performance_task', collect())->pluck('score')->toArray(),
                'Prelim Quiz Scores' => $prelimScores->get('quiz', collect())->pluck('score')->toArray(),
                'Prelim Recitation Scores' => $prelimScores->get('recitation', collect())->pluck('score')->toArray(),
                'Prelim Lec Exam Scores' => $prelimScores->get('lec', collect())->pluck('score')->toArray(),
                'Prelim Lab Exam Scores' => $prelimScores->get('lab', collect())->pluck('score')->toArray(),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Student Number',
            'First Name',
            'Middle Name',
            'Last Name',
            'Course',
            'Section',
            'Subject',
            'Prelim Performance Task Scores',
            'Prelim Quiz Scores',
            'Prelim Recitation Scores',
            'Prelim Lec Exam Scores',
            'Prelim Lab Exam Scores',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Apply styles to the sheet
        return [
            // Style the first row as bold (headings)
            1 => ['font' => ['bold' => true]],
            // Additional styling can be added here
        ];
    }

    public function title(): string
    {
        return 'Prelim Grades'; // Custom sheet name
    }
}
