// Alpine ships with Livewire. Trix is the formatted-text editor behind <x-editor>.
import 'trix';
import 'trix/dist/trix.css';

// Editors are for text only: no file or image attachments.
document.addEventListener('trix-file-accept', (event) => event.preventDefault());
