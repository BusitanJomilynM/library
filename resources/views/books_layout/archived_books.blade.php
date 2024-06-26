@extends('master_layout.master')
@section('Title', 'Books')
@section('content')
<h2 style="text-align: center;">Books</h2>

<div>
<form style="margin:auto;max-width:300px">
    <input type="text" class="form-control mr-sm-2" placeholder="Search Books" name="search"  value="{{ request('search') }}">
    <button type="submit" class="btn btn-danger">
    <i class="fa fa-search"></i>
    </button>
</form>
</div>


<a class="btn btn-primary my-2 my-sm-0" href="{{ route('books.index') }}">Return</a> <br><br>

<table class="table table-hover table-bordered" style="width:100%">
<thead class="thead-dark">
  <tr align="center">
    <th>Title</th>
    <th>Author</th>
    <th>Copyright Year</th>
    <th>Location</th>
    <th>Reason for archive</th>
    <th>Actions</th>
  </tr>
</thead>

@forelse($archives as $archive)
<tbody>
  <tr align="center">
    <td>{{$archive->book_title}}</td>
    <td>{{$archive->book_author}}</td>
    <td>{{$archive->book_copyrightyear}}</td>
    <td>{{$archive->book_sublocation}}</td>
    <!-- <!-- <td><?php 
            
           
                    // $x = $archive->book_subject;
                    // $charactersToRemove = ['"', "[", "]"];
                    // $s = str_replace($charactersToRemove, "", $x);

                    // $words = explode(',', $s);

                    // $count = count($words);

                    // foreach ($subjects as $subject){
                    //     foreach ($words as  $key => $word) {
                    //         if( $word == $subject->id){
                    //         echo $subject->subject_name;
                    //         if ($key < $count - 1) {
                    //             echo " ,";
                    //           } 
                    //         }
                    //     }
                    // }   ?>
                   
                      
    </td> -->
    <td>
    @if($archive->archive_reason == 1)  
      Missing
    @elseif($archive->archive_reason == 2)
      Discarded
    @elseif($archive->archive_reason == 3)
      Damaged

    @endif
    </td>
    <td>
    <div class="flex-parent jc-center">
            <form action="{{ route('restoreBook', $archive->id) }}" method="POST">
                {{ csrf_field() }}
                {{ method_field('GET') }}
                <button type="submit" class="btn btn-success" role="button">Restore</button>

                <a data-toggle="modal" class="btn btn-danger" data-target="#deleteBookModal_{{$archive->id}}" data-action="{{ route('books.destroy', $archive->id) }}">Delete</a>
            </form>
      </div>
    </td>
  </tr>
</tbody>

<!-- Delete BookModal -->
<div class="modal fade" id="deleteBookModal_{{$archive->id}}" data-backdrop="static" tabindex="-1" role="dialog"
    aria-labelledby="deleteUserModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="deleteUserModalLabel">Are you sure you want to delete this book?</h5>
            
          </div>
          <form action="{{ route('books.destroy', $archive->id) }}" method="POST">
            <div class="modal-body">
              @csrf
              @method('DELETE')
              <h5 class="text-center">Delete book {{$archive->book_title}}?
               
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-danger">Delete</button>
            </div>
        </form>
        </div>
      </div>
    </div>

@empty
<tr align="center"> <td colspan="13"><h3>No Entry Found</h3></td></tr> 
@endforelse

</table>
<div class="d-flex">
    <div class="mx-auto">
<?php echo $archives->render(); ?>
  </div>
</div>


<style> 
form { 
  display: flex; 
}
input[type=text] 
{ flex-grow: 1; 
}

.flex-parent {
  display: flex;
}

.jc-center {
  justify-content: center;
}
</style>




@endsection