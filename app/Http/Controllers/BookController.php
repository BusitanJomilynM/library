<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Requests\UpdateArchiveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; 
use App\Models\Book;
use App\Models\Tag;
use App\Models\User;
use App\Models\Course;
use App\Models\Keyword;
use App\Models\Subject;
use Illuminate\Support\Facades\Auth; 
use Illuminate\Pagination\Paginator;
//  use Barryvdh\DomPDF\Facade\Pdf;
// use Codedge\Fpdf\Fpdf\Fpdf;
use Barryvdh\DomPDF\Facade as PDF;
//  use PDF;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use App\Models\archiveUpdate;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Response;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;


class BookController extends Controller{

    public function __construct() {
        $this->middleware('preventBackHistory'); 
        $this->middleware('auth'); 
    
    } 
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request): \Illuminate\Contracts\View\View    {
        $user = Auth::user();
        $books = Book::paginate(10);
        $keywords = Keyword::all();
        $subjects = Subject::all();
    
        Paginator::useBootstrap();
        
        if (request('search')) {
            $books = Book::where('book_title', 'like', '%' . request('search') . '%')
                ->orWhere('book_callnumber', 'like', '%' . request('search') . '%')
                ->orWhere('book_barcode', 'like', '%' . request('search') . '%')
                ->orWhere('book_author', 'like', '%' . request('search') . '%')
                ->orWhere('book_copyrightyear', 'like', '%' . request('search') . '%')
                ->orWhere('book_sublocation', 'like', '%' . request('search') . '%')
                ->orWhere('book_keyword', 'like', '%' . request('search') . '%')
                ->orWhere('book_subject', 'like', '%' . request('search') . '%')
                ->orderBy('book_title', 'asc')
                ->paginate(10);
            
            $books->appends(['search' => request('search')]); // Add search query parameter to pagination links
        } else {
            $books = DB::table('books')
                ->select('*', 'books.id as bookId')
                ->paginate(10);
        }
    
        return view('books_layout.books_list', ['books'=>$books,'user'=>$user,'subjects'=>$subjects]);
    }    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request): View
    {
        $user = Auth::user();
        $books = Book::paginate(10);
        // $keywords = Keyword::all();
        $subjects = Subject::all();        
        if ($user->type === 'technician librarian') {
            // Assuming you have some logic to generate the barcode
            
            return view('books_layout.books_list', ['books'=>$books,'user'=>$user,'subjects'=>$subjects]);
        } else {
            return redirect()->back();
        }
    }

    public function generateBarcode(Request $request)
    {
        $user = Auth::user();
        if ($user->type === 'technician librarian') {
            $barcode = $this->generateUniqueBarcode();
            // Display the generated barcode in the same view
            return view('books_layout.books_list', ['barcode' => $barcode]);
        } else {
            return redirect()->back();
        }
    }

    protected function generateUniqueBarcode()
    {
        $barcode = 'T' . rand(1, 99999);

        // Check if the generated barcode already exists in the database
        while (Book::where('book_barcode', $barcode)->exists()) {
            $barcode = 'T' . rand(1, 99999);
        }

        return $barcode;
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreBookRequest $request): \Illuminate\Http\RedirectResponse
{
    $bookBarcode = $request->input('book_barcode');
    $existingBook = Book::where('book_barcode', $bookBarcode)->first();

    $data = $request->all();

    // Split authors by comma and trim any extra whitespaces
    $authors = explode(',', $data['book_author']);
    $authors = array_map('trim', $authors);

    // Remove empty elements from the array
    $authors = array_filter($authors);

    $data['book_author'] = implode(', ', $authors);
    if (is_array($data['book_subject'])) {
        $data['book_subject'] = implode(', ', array_map('trim', $data['book_subject']));
    }

    // Encode subjects as JSON
    $data['book_subject'] = json_encode($request->book_subject);

    // Extract the last 4 characters of book_callnumber as book_copyrightyear
    $bookCallNumber = $data['book_callnumber'];
    $bookCopyrightYear = substr($bookCallNumber, -4);

    // Add book_copyrightyear to the data array
    $data['book_copyrightyear'] = $bookCopyrightYear;

    // Check if the book call number matches any existing book
    $existingBookWithSameCallNumber = Book::where('book_callnumber', $bookCallNumber)->first();

    if ($existingBookWithSameCallNumber) {
        // If book title matches, create the book
        if ($existingBookWithSameCallNumber->book_title === $data['book_title']) {
            // Store the input data in the session
            $request->session()->put('book_data', $data);

            Book::create($data);

            if ($existingBook) {
                return redirect()->route('books.create')->withInput($data)->with('error', 'A book with that barcode is already registered.');
            } else {
                return redirect()->route('books.index');
            }
        } else {
            // If book title does not match, redirect back with error
            return redirect()->route('books.create')->withInput($data)->with('error', 'A book with that call number already exists, but with a different title.');
        }
    } else {
        // If no book with the same call number exists, create the book
        // Store the input data in the session
        $request->session()->put('book_data', $data);

        Book::create($data);

        if ($existingBook) {
            return redirect()->route('books.create')->withInput($data)->with('error', 'A book with that barcode is already registered.');
        } else {
            return redirect()->route('books.index');
        }
    }
}

        /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id): Response
    {
        $book = Book::findOrFail($id);
    
        // Assuming you have a view called 'books.show' to display the book details
        return response()->view('books.show', ['book' => $book]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Book $book): View
    {
        $user = Auth::user();
        if ($user->type === 'technician librarian') {
            return view('books_layout.view_bookdetails', compact('book'));
        } else {
            return redirect()->back();
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     */
    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $user = Auth::user();
        if ($user->type !== 'technician librarian') {
            return redirect()->back()->with('error', 'You do not have permission to perform this action.');
        }
    
        // Extract book_barcode from the request
        $bookBarcode = $request->input('book_barcode');
    
        // Remove the book_barcode from the request
        $requestData = $request->except('book_barcode');
    
        // Update the main book
        $book->update($requestData); 
    
        // Find all books with the same book_callnumber but different book_barcode
        $matchingBooks = Book::where('book_callnumber', $book->book_callnumber)
                            ->where('book_barcode', '<>', $bookBarcode)
                            ->get();
    
        // Update each matching book
        foreach ($matchingBooks as $matchingBook) {
            $matchingBook->update($requestData);
        }
    
        return redirect()->route('books.index')->with('success', 'Book successfully updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Book $book): \Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();
        if ($user->type === 'technician librarian') {
            $book->delete();
            return redirect()->route('archive')->with('success', 'Book Deleted');
        } else {
            return redirect()->back();
        }
    }
    

        
    public function archiveBook(UpdateArchiveRequest $request, Book $book){
        if ($book->archive_reason != 0) {
            // If the book is not archived due to being lost, erase the barcode
            $book->update(['book_barcode' => null]);
        }
        $book->update($request->all()); 
        return view('books_layout.view_bookdetails', ['book'=>$book]);
    }
    

    public function archiveUpdate(UpdateArchiveRequest $request, Book $book)
    {
        if ($book->archive_reason != 0) {
            // If the book is not archived due to being lost, remove the barcode
            $book->update(['book_barcode' => null]);
        }
    
        $book->update($request->all()); 
        $book->status = 1;
        $book->save();
        
        return redirect()->route('archive')->with('success', 'Book archived');
    }
    
    public function restoreBook(Request $request, Book $book)
    {
        $archiveReason = $book->archive_reason;
    
        if ($archiveReason == 1) {
            // If the book is archived due to being lost, retain the existing barcode
            $barcode = $book->book_barcode;
        } else {
            // If the book is archived due to being old or damaged, generate a new barcode
            $barcode = $this->generateUniqueBarcode();
            $book->book_barcode = $barcode;
        }
        
        // Update archive reason to null and set status to indicate the book is restored
        $book->update(['archive_reason' => null, 'status' => 0]);
        
        // Optionally, update other attributes based on the request
        $book->update($request->all()); 
        
        return redirect()->route('archive')->with('success', 'Book restored. Barcode: ' . $barcode);
    }
    
    

    public function restoreUpdate(UpdateBookRequest $request, Book $book)
    {
        $book->update($request->all()); 

        return redirect()->route('archive')->with('success','Book restored');
    }

    public function archive(){

        $user = Auth::user();
        
        if($user->type === 'technician librarian') {
        $archives = Book::where('status', 'like', '1')->paginate(10);
        }
        elseif($user->type === 'staff librarian') {
        $archives = Book::where('status', 'like', '1')->paginate(10);
        }
        else{
            return redirect()->back();
        }

        $subjects = Subject::all();

        return view('books_layout.archived_books', ['archives'=>$archives,'user'=>$user, 'subjects'=>$subjects]);

    }
    public function view_bookdetails(Book $book)
    {
        $user = Auth::user();
        
        // Check if the user is authorized to suggest subjects and keywords
        $canSuggest = $user->type === 'teacher' || $user->type === 'department representative';
    
        // Load keywords and subjects
        // $keywords = Keyword::all();
        $subjects = Subject::all();
        $barcode = $book->book_barcode;
    
        // Return the view with necessary data
        return view('books_layout.view_bookdetails', compact('book', 'user', 'barcode', 'subjects', 'canSuggest'));
    }
    
    
    public function book_createcopy(Request $request, Book $book)
    {
        $barcode = $this->generateUniqueBarcode();
        return view('books_layout.view_bookdetails', compact('book','barcode'));
    }

    

    
    public function validateMaterialType(Request $request)
    {
        $request->validate([
            'material_type' => 'required|in:Book,JournalMagazine,DocumentaryFilm,DVDVCD,MapsGlobes,Other',
            'other_material_type' => Rule::requiredIf(function () use ($request) {
                return $request->input('material_type') == 'Other';
            }) . '|min:2|max:40',
        ], [
            'other_material_type.required' => 'The Other field is required when Material Type is Other.',
        ]);

    }
    public function booklistPdf(Request $request, Book $book)
    {
        $courses = Course::all();    
        $subjects = Subject::all();  
        // $keywords = Keyword::all(); 
        $books = Book::all();    
        $user = Auth::user();
    
        $course_name = $request->input('course');
        $callNumberPrefixes = explode(',', $request->input('callNumberPrefix'));

        $course = Course::where('course_name', $course_name)->first();
        $course_code = $course ? $course->course_code : null;

        $prefix = strtoupper($request->input('callNumberPrefix'));


        $filteredBooksSets = []; // Array to hold filtered books from different sets
        $bookStatsSets = []; // Array to hold book stats for different sets
        $subjectNamesListSets = []; // Array to hold subject names for different sets
        $subjectCodesListSets = []; // Array to hold subject codes for different sets
        
        $setCount = 1;
        while ($request->has("subject_$setCount") && $request->has("keyword_$setCount")) {
            // Initialize subject-specific arrays for each set
            $subjectNamesList = [];
            $subjectNamee = [];
            $subjectCodesList = [];
            $keywordsList = [];
            $subjectIDList = []; // Reset subject ID list for each set
        
            $filteredBooks = collect(); // Reset filtered books collection for each set
        
            $subjectNames = $request->input("subject_$setCount");
            $subject = Subject::where('id', $subjectNames)->first();
            if ($subject) {
                $subjectIDList[] = $subject->id;
                $subjectNamesList[] = $subjectNames;
                $subjectNamee[] = $subject->subject_name;
                $subjectCodesList[] = $subject->subject_code; // Store subject code
                $keywordsList[] = $request->input("keyword_$setCount");

                // $keywordIds = $request->input("keyword_$setCount");
                // $keywordsListt = Keyword::whereIn('id', $keywordIds)->pluck('keyword')->toArray();

            }
        

            foreach ($books as $book) {
                $jsonSubject = $book->book_subject;
                $subjectArray = json_decode($jsonSubject, true);
                $jsonKeyword = $book->book_keyword;
                $keywordArray = json_decode($jsonKeyword, true);
                $titleWords = explode(' ', strtolower($book->book_title));
            
                if ($book->archive_reason) {
                    continue; // Skip the book if it is archived
                }
            
                if ($prefix && strpos($book->book_callnumber, $prefix) !== 0) {
                    continue; // Skip the book if the prefix doesn't match
                }
            
                $matched = false; // Flag to indicate if any match is found
            
                // Check if any subject name provided by the user matches any subject in the book
                foreach ($subjectIDList as $subjectid) {
                    if (in_array($subjectid, $subjectArray)) {
                        $filteredBooks->push($book);
                        $matched = true;
                        break; // Once a match is found, break the loop for this book
                    }
                }
            
                // Check if keywordArray is not null and if any match is already found
                if (!is_null($keywordArray) && !$matched) {
                    // Check if any keyword provided by the user matches any keyword in the book_subject
                    foreach ($keywordsList as $keywords) {
                        $keywords = preg_split('/,/', $keywords);
                        foreach ($keywords as $keyword) {
                            if (in_array($keyword, $keywordArray)) {
                                $filteredBooks->push($book);
                                $matched = true;
                                break 2; // Break both inner and outer loop once a match is found
                            }
                        }
                    }
                }
            
                // Check if any word in the title matches any keyword
                if (!$matched) {
                    foreach ($keywordsList as $keywords) {
                        $keywords = preg_split('/,/', $keywords);
                        foreach ($keywords as $keyword) {
                            foreach ($titleWords as $titleWord) {
                                if (strpos(strtolower($titleWord), strtolower($keyword)) !== false) {
                                    $filteredBooks->push($book);
                                    $matched = true;
                                    break 3; // Break both inner and outer loops once a match is found
                                }
                            }
                        }
                    }
                }
            }
                                                // Remove duplicate books based on ID
            $filteredBooks = $filteredBooks->unique('id');
        
            // Store filtered books for the current set
            $filteredBooksSets[$setCount] = $filteredBooks;
        
            // Store subject names and codes for the current set
            $subjectNamesListSets[$setCount] = $subjectNamee;
            $subjectCodesListSets[$setCount] = $subjectCodesList;
        
            $encounteredCallNumbers = [];

            foreach ($filteredBooksSets as $setCount => $filteredBooks) {
                // Store filtered books for the current set
                $filteredBooksSets[$setCount] = $filteredBooks;
            
                // Store subject names and codes for the current set
                $subjectNamesListSets[$setCount] = $subjectNamee;
                $subjectCodesListSets[$setCount] = $subjectCodesList;
            
                // Calculate book stats for the current set
                $bookStats = [];
                $uniqueCallNumbers = [];
            
                foreach ($filteredBooks as $book) {
                    $callNumber = $book->book_callnumber;
            
                    // Count unique call numbers
                    if (!isset($encounteredCallNumbers[$callNumber])) {
                        $encounteredCallNumbers[$callNumber] = true; // Mark the call number as encountered
                        $uniqueCallNumbers[] = $callNumber; // Add the call number to the array of unique call numbers
                    }
            
                    if (!isset($bookStats[$callNumber])) {
                        $bookStats[$callNumber] = [
                            'title' => $book->book_title,
                            'call_number' => $callNumber,
                            'author' => $book->book_author,
                            'totalCopies' => 1,
                            'copyright' => $book->book_copyrightyear,
                        ];
                    } else {
                        $bookStats[$callNumber]['totalCopies'] += 1;
                    }
                }
            
                // Store the count of unique call numbers for the current set
                $uniqueCallNumbersCount = count($uniqueCallNumbers);
                $uniqueCallNumbersSets[$setCount] = $uniqueCallNumbersCount;
            
                // Store book stats for the current set
                $bookStatsSets[$setCount] = $bookStats;
            }
            
            $bookStatsSets[$setCount] = $bookStats;
            $setCount++;
        }
        
        
        // Now you have filtered books, book stats, subject names, and subject codes for each set
        // You can use $filteredBooksSets, $bookStatsSets, $subjectNamesListSets, and $subjectCodesListSets to pass to the PDF view
        
        if (!empty($filteredBooksSets)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('books_layout.pdf_view', compact('user', 'course_name', 'course_code', 'bookStatsSets', 'filteredBooksSets', 'subjectNamesListSets', 'subjectCodesListSets', 'uniqueCallNumbersSets'))->setPaper('a4', 'portrait');            
            return $pdf->stream('booklist.pdf');
        } else {
            return view('books_layout.booklist_pdf', ['books' => $books, 'courses' => $courses, 'subjects' => $subjects]);
        }
            }            
    
            public function collectionAnalysis(Request $request, Book $book)
            {
                $courses = Course::all();    
                $subjects = Subject::all();  
                $books = Book::all();    
                $user = Auth::user();
            
                $course_name = $request->input('course');
                $callNumberPrefixes = explode(',', $request->input('callNumberPrefix'));
        
                $course = Course::where('course_name', $course_name)->first();
                $course_code = $course ? $course->course_code : null;

                $prefix = strtoupper($request->input('callNumberPrefix'));
        
                $filteredBooksSets = []; // Array to hold filtered books from different sets
                $bookStatsSets = []; // Array to hold book stats for different sets
                $subjectNamesListSets = []; // Array to hold subject names for different sets
                $subjectCodesListSets = []; // Array to hold subject codes for different sets
                $setCount = 1; // Reset $setCount for each iteration
                while ($request->has("subject_$setCount") && $request->has("keyword_$setCount")) {
                    // Reset variables for each iteration
                    $subjectNamesList = [];
                    $subjectNamee = [];
                    $subjectCodesList = [];
                    $keywordsList = [];
                    $subjectIDList = [];
                    $filteredBooks = collect();
                
                    $subjectNames = $request->input("subject_$setCount");
                    $subject = Subject::where('id', $subjectNames)->first();
                    if ($subject) {
                        $subjectIDList[] = $subject->id;
                        $subjectNamesList[] = $subjectNames;
                        $subjectNamee[] = $subject->subject_name;
                        $subjectCodesList[] = $subject->subject_code; // Store subject code
                        $keywordsList[] = $request->input("keyword_$setCount");
                        // $keywordIds = $request->input("keyword_$setCount");
                        // $keywordsListt = Keyword::whereIn('id', $keywordIds)->pluck('keyword')->toArray();
        
                    }
                
                    foreach ($books as $book) {
                        $jsonSubject = $book->book_subject;
                        $subjectArray = json_decode($jsonSubject, true);
                        $jsonKeyword = $book->book_keyword;
                        $keywordArray = json_decode($jsonKeyword, true);
                        $titleWords = explode(' ', strtolower($book->book_title));
                    
                        if ($book->archive_reason) {
                            continue; // Skip the book if it is archived
                        }
                    
                        if ($prefix && strpos($book->book_callnumber, $prefix) !== 0) {
                            continue; // Skip the book if the prefix doesn't match
                        }
                    
                        $matched = false; // Flag to indicate if any match is found
                    
                        // Check if any subject name provided by the user matches any subject in the book
                        foreach ($subjectIDList as $subjectid) {
                            if (in_array($subjectid, $subjectArray)) {
                                $filteredBooks->push($book);
                                $matched = true;
                                break; // Once a match is found, break the loop for this book
                            }
                        }
                    
                        // Check if keywordArray is not null and if any match is already found
                        if (!is_null($keywordArray) && !$matched) {
                            // Check if any keyword provided by the user matches any keyword in the book_subject
                            foreach ($keywordsList as $keywords) {
                                $keywords = preg_split('/,/', $keywords);
                                foreach ($keywords as $keyword) {
                                    if (in_array($keyword, $keywordArray)) {
                                        $filteredBooks->push($book);
                                        $matched = true;
                                        break 2; // Break both inner and outer loop once a match is found
                                    }
                                }
                            }
                        }
                    
                        // Check if any word in the title matches any keyword
                        if (!$matched) {
                            foreach ($keywordsList as $keywords) {
                                $keywords = preg_split('/,/', $keywords);
                                foreach ($keywords as $keyword) {
                                    foreach ($titleWords as $titleWord) {
                                        if (strpos(strtolower($titleWord), strtolower($keyword)) !== false) {
                                            $filteredBooks->push($book);
                                            $matched = true;
                                            break 3; // Break both inner and outer loops once a match is found
                                        }
                                    }
                                }
                            }
                        }
                    }
                                            
                    // Remove duplicates from filtered books
                    $filteredBooks = $filteredBooks->unique('id');
                
                    // Store filtered books for the current set
                    $filteredBooksSets[$setCount] = $filteredBooks;
                
                    // Store subject names and codes for the current set
                    $subjectNamesListSets[$setCount] = $subjectNamee;
                    $subjectCodesListSets[$setCount] = $subjectCodesList;
                
                    // Calculate book stats for the current set
                    $bookStats = [];
                    foreach ($filteredBooks as $book) {
                        $callNumber = $book->book_callnumber;
                
                        if (!isset($bookStats[$callNumber])) {
                            $bookStats[$callNumber] = [
                                'totalCopies' => 1,
                                'copyright' => $book->book_copyrightyear,
                            ];
                        } else {
                            $bookStats[$callNumber]['totalCopies'] += 1;

                        }
                    }
                
                    // Store book stats for the current set
                    $bookStatsSets[$setCount] = $bookStats;
                
                    $setCount++; // Increment set count for next iteration
                }
                                
                if (!empty($filteredBooksSets)) {
                    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('books_layout.pdf_collection', compact('user', 'course_name', 'course_code', 'bookStatsSets', 'filteredBooksSets', 'subjectNamesListSets', 'subjectCodesListSets'))->setPaper('a4', 'landscape');
                    return $pdf->stream('collectionanalysis.pdf');
                } else {
                    return view('books_layout.booklist_pdf', ['books' => $book, 'courses' => $courses, 'subjects' => $subjects]);
                }
                    }
                    
    }      
      
        //     return view('books_layout.booklist_pdf', ['books' => $book, 'courses' => $courses, 'subjects' => $subjects, 'keywords' => $keywords]);
