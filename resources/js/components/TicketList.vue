<!--
TODO: Ticket List Component
Requirements from specification:
- /tickets route - table or card list
- Client-side filter, text search, simple pagination
- Column/label for category, confidence (0-1), tooltip or icon for explanation, badge if note present
- "New Ticket" modal/form
- "Classify" button per ticket shows spinner until done
- Inline category dropdown - has to be implemented
- CSV export of list
-->

<template>
  <div class="ticket-list">
    <!-- Header with search and new ticket button -->
    <div class="ticket-list__header">
      <h2 class="ticket-list__title">Support Tickets</h2>
      <div class="ticket-list__actions">
        <button @click="showNewTicketModal = true" class="btn btn--primary">
          <PlusIcon class="btn__icon" />
          New Ticket
        </button>
        <button @click="exportCSV" class="btn btn--secondary">
          <ArrowDownTrayIcon class="btn__icon" />
          Export CSV
        </button>
      </div>
    </div>

    <!-- Filters and Search -->
    <div class="ticket-list__filters">
      <div class="filter-group">
        <input
          v-model="searchTerm"
          @input="debouncedSearch"
          type="text"
          placeholder="Search tickets..."
          class="filter-group__search"
        >
        
        <select v-model="statusFilter" class="filter-group__select">
          <option value="">All Statuses</option>
          <option value="open">Open</option>
          <option value="in_progress">In Progress</option>
          <option value="resolved">Resolved</option>
          <option value="closed">Closed</option>
        </select>
        
        <select v-model="categoryFilter" class="filter-group__select">
          <option value="">All Categories</option>
          <option value="technical">Technical Support</option>
          <option value="billing">Billing & Payment</option>
          <option value="account">Account Management</option>
          <option value="feature_request">Feature Request</option>
          <option value="bug_report">Bug Report</option>
          <option value="general">General Inquiry</option>
        </select>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="ticket-list__loading">
      <div class="spinner"></div>
      <p>Loading tickets...</p>
    </div>

    <!-- Tickets Table/Cards -->
    <div v-else-if="paginatedTickets.length > 0" class="ticket-list__content">
      <!-- Table View (default) -->
      <div v-if="viewMode === 'table'" class="ticket-table">
        <table class="ticket-table__table">
          <thead class="ticket-table__head">
            <tr class="ticket-table__row ticket-table__row--header">
              <th class="ticket-table__cell ticket-table__cell--header">ID</th>
              <th class="ticket-table__cell ticket-table__cell--header">Subject</th>
              <th class="ticket-table__cell ticket-table__cell--header">Status</th>
              <th class="ticket-table__cell ticket-table__cell--header">Category</th>
              <th class="ticket-table__cell ticket-table__cell--header">Confidence</th>
              <th class="ticket-table__cell ticket-table__cell--header">Note</th>
              <th class="ticket-table__cell ticket-table__cell--header">Actions</th>
            </tr>
          </thead>
          <tbody class="ticket-table__body">
            <tr
              v-for="ticket in paginatedTickets"
              :key="ticket.id"
              class="ticket-table__row"
              @click="viewTicket(ticket.id)"
            >
              <td class="ticket-table__cell">
                <span class="ticket-id">{{ ticket.id.slice(-8) }}</span>
              </td>
              <td class="ticket-table__cell">
                <div class="ticket-subject">
                  <span class="ticket-subject__text">{{ ticket.subject }}</span>
                </div>
              </td>
              <td class="ticket-table__cell">
                <span class="status-badge" :class="`status-badge--${ticket.status}`">
                  {{ getStatusLabel(ticket.status) }}
                </span>
              </td>
              <td class="ticket-table__cell">
                <select
                  :value="ticket.category || ''"
                  @change="updateTicketCategory(ticket.id, $event.target.value)"
                  @click.stop
                  @mousedown.stop
                  class="category-select"
                >
                  <option value="">Unclassified</option>
                  <option value="technical">Technical Support</option>
                  <option value="billing">Billing & Payment</option>
                  <option value="account">Account Management</option>
                  <option value="feature_request">Feature Request</option>
                  <option value="bug_report">Bug Report</option>
                  <option value="general">General Inquiry</option>
                </select>
              </td>
              <td class="ticket-table__cell">
                <div v-if="ticket.confidence" class="confidence">
                  <div class="confidence__bar">
                    <div 
                      class="confidence__fill" 
                      :style="{ width: (ticket.confidence * 100) + '%' }"
                    ></div>
                  </div>
                  <span class="confidence__text">{{ Math.round(ticket.confidence * 100) }}%</span>
                  <div v-if="ticket.explanation" class="tooltip">
                    <InformationCircleIcon class="tooltip__trigger" />
                    <div class="tooltip__content">{{ ticket.explanation }}</div>
                  </div>
                </div>
              </td>
              <td class="ticket-table__cell">
                <DocumentTextIcon v-if="ticket.note" class="note-badge" />
              </td>
              <td class="ticket-table__cell">
                <button
                  @click.stop="classifyTicket(ticket.id)"
                  :disabled="classifyingTickets.includes(ticket.id)"
                  class="btn btn--small"
                >
                  <span v-if="classifyingTickets.includes(ticket.id)" class="spinner spinner--small"></span>
                  <span v-else>
                    <SparklesIcon class="btn__icon" />
                    Classify
                  </span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Card View -->
      <div v-else class="ticket-cards">
        <div
          v-for="ticket in paginatedTickets"
          :key="ticket.id"
          class="ticket-card"
          @click="viewTicket(ticket.id)"
        >
          <div class="ticket-card__header">
            <span class="ticket-id">{{ ticket.id.slice(-8) }}</span>
            <span class="status-badge" :class="`status-badge--${ticket.status}`">
              {{ getStatusLabel(ticket.status) }}
            </span>
          </div>
          
          <div class="ticket-card__body">
            <h3 class="ticket-card__subject">{{ ticket.subject }}</h3>
            <p class="ticket-card__body-preview">{{ ticket.body.substring(0, 100) }}...</p>
          </div>
          
          <div class="ticket-card__footer">
            <div class="ticket-card__meta">
              <select
                :value="ticket.category || ''"
                @change="updateTicketCategory(ticket.id, $event.target.value)"
                @click.stop
                @mousedown.stop
                class="category-select category-select--small"
              >
                <option value="">Unclassified</option>
                <option value="technical">Technical Support</option>
                <option value="billing">Billing & Payment</option>
                <option value="account">Account Management</option>
                <option value="feature_request">Feature Request</option>
                <option value="bug_report">Bug Report</option>
                <option value="general">General Inquiry</option>
              </select>
              <div v-if="ticket.confidence" class="confidence confidence--small">
                <span class="confidence__text">{{ Math.round(ticket.confidence * 100) }}%</span>
                <div v-if="ticket.explanation" class="tooltip">
                  <InformationCircleIcon class="tooltip__trigger" />
                  <div class="tooltip__content">{{ ticket.explanation }}</div>
                </div>
              </div>
              <DocumentTextIcon v-if="ticket.note" class="note-badge" />
            </div>
            
            <button
              @click.stop="classifyTicket(ticket.id)"
              :disabled="classifyingTickets.includes(ticket.id)"
              class="btn btn--small"
            >
              <span v-if="classifyingTickets.includes(ticket.id)" class="spinner spinner--small"></span>
              <span v-else>
                <SparklesIcon class="btn__icon" />
                Classify
              </span>
            </button>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div class="pagination">
        <button
          @click="currentPage--"
          :disabled="currentPage === 1"
          class="pagination__btn"
        >
          Previous
        </button>
        
        <span class="pagination__info">
          Page {{ currentPage }} of {{ totalPages }} ({{ totalTickets }} total)
        </span>
        
        <button
          @click="currentPage++"
          :disabled="currentPage === totalPages"
          class="pagination__btn"
        >
          Next
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="ticket-list__empty">
      <p>No tickets found</p>
      <button @click="showNewTicketModal = true" class="btn btn--primary">
        <PlusIcon class="btn__icon" />
        Create First Ticket
      </button>
    </div>

    <!-- New Ticket Modal -->
    <div v-if="showNewTicketModal" class="modal">
      <div class="modal__backdrop" @click="showNewTicketModal = false"></div>
      <div class="modal__content">
        <div class="modal__header">
          <h3 class="modal__title">New Ticket</h3>
          <button @click="showNewTicketModal = false" class="modal__close">
            <XMarkIcon class="modal__close-icon" />
          </button>
        </div>
        
        <form @submit.prevent="createTicket" class="modal__body">
          <div class="form-group">
            <label class="form-group__label">Subject *</label>
            <input
              v-model="newTicket.subject"
              type="text"
              required
              class="form-group__input"
              :class="{ 'form-group__input--error': validationErrors.subject }"
            >
            <div v-if="validationErrors.subject" class="form-group__error">
              {{ validationErrors.subject }}
            </div>
          </div>
          
          <div class="form-group">
            <label class="form-group__label">Body *</label>
            <textarea
              v-model="newTicket.body"
              required
              rows="4"
              class="form-group__textarea"
              :class="{ 'form-group__textarea--error': validationErrors.body }"
            ></textarea>
            <div v-if="validationErrors.body" class="form-group__error">
              {{ validationErrors.body }}
            </div>
          </div>
          
          <div class="form-group">
            <label class="form-group__label">Status</label>
            <select v-model="newTicket.status" class="form-group__select">
              <option value="open">Open</option>
              <option value="in_progress">In Progress</option>
              <option value="resolved">Resolved</option>
              <option value="closed">Closed</option>
            </select>
          </div>
          
          <div class="modal__actions">
            <button type="button" @click="showNewTicketModal = false" class="btn btn--secondary">
              Cancel
            </button>
            <button type="submit" :disabled="submitting" class="btn btn--primary">
              <span v-if="submitting" class="spinner spinner--small"></span>
              <span v-else>
                <PlusIcon class="btn__icon" />
                Create Ticket
              </span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { 
  PlusIcon, 
  ArrowDownTrayIcon, 
  InformationCircleIcon, 
  DocumentTextIcon, 
  SparklesIcon, 
  XMarkIcon 
} from '@heroicons/vue/24/outline'

export default {
  name: 'TicketList',
  components: {
    PlusIcon,
    ArrowDownTrayIcon,
    InformationCircleIcon,
    DocumentTextIcon,
    SparklesIcon,
    XMarkIcon
  },
  data() {
    return {
      tickets: [],
      loading: true,
      searchTerm: '',
      statusFilter: '',
      categoryFilter: '',
      viewMode: 'table', // 'table' or 'cards'
      currentPage: 1,
      itemsPerPage: 10,
      classifyingTickets: [],
      showNewTicketModal: false,
      submitting: false,
      newTicket: {
        subject: '',
        body: '',
        status: 'open'
      },
      validationErrors: {},
      searchTimeout: null
    }
  },
  computed: {
    // Client-side filtering removed - now using server-side filtering
    paginatedTickets() {
      return this.tickets; // Server-side pagination, so just return the current page data
    },
    // totalTickets and totalPages are now set from server response
  },
  async mounted() {
    await this.fetchTickets();
  },
  watch: {
    currentPage() {
      this.fetchTickets();
    },
    searchTerm() {
      this.debouncedSearch();
    },
    statusFilter() {
      this.currentPage = 1;
      this.fetchTickets();
    },
    categoryFilter() {
      this.currentPage = 1;
      this.fetchTickets();
    }
  },
  methods: {
    async fetchTickets() {
      try {
        this.loading = true;
        
        // Build query parameters
        const params = new URLSearchParams();
        params.append('page', this.currentPage.toString());
        params.append('per_page', '10');
        
        if (this.searchTerm) {
          params.append('search', this.searchTerm);
        }
        if (this.statusFilter) {
          params.append('status', this.statusFilter);
        }
        if (this.categoryFilter) {
          params.append('category', this.categoryFilter);
        }
        
        const response = await fetch(`/api/tickets?${params.toString()}`);
        if (!response.ok) throw new Error('Failed to fetch tickets');
        const data = await response.json();
        
        this.tickets = data.data || data;
        this.totalTickets = data.total || data.length;
        this.totalPages = data.last_page || Math.ceil(this.totalTickets / 15);
      } catch (error) {
        console.error('Error fetching tickets:', error);
        alert('Failed to load tickets');
      } finally {
        this.loading = false;
      }
    },
    
    debouncedSearch() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => {
        this.currentPage = 1; // Reset to first page when searching
        this.fetchTickets();
      }, 300);
    },
    
    async classifyTicket(ticketId) {
      if (this.classifyingTickets.includes(ticketId)) return;
      
      try {
        this.classifyingTickets.push(ticketId);
        
        const response = await fetch(`/api/tickets/${ticketId}/classify`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
          }
        });
        
        if (!response.ok) throw new Error('Classification failed');
        
        // Poll for classification completion
        await this.pollForClassificationCompletion(ticketId);
        
      } catch (error) {
        console.error('Error classifying ticket:', error);
        alert('Failed to classify ticket');
      } finally {
        this.classifyingTickets = this.classifyingTickets.filter(id => id !== ticketId);
      }
    },

    async pollForClassificationCompletion(ticketId) {
      const maxAttempts = 30; // 30 seconds max
      const pollInterval = 1000; // 1 second
      let attempts = 0;
      
      while (attempts < maxAttempts) {
        try {
          const response = await fetch(`/api/tickets/${ticketId}`);
          if (!response.ok) throw new Error('Failed to fetch ticket');
          
          const data = await response.json();
          const ticket = data.data || data;
          
          // Check if classification is complete (has category and confidence)
          if (ticket.category && ticket.confidence !== null) {
            // Classification complete, refresh the list
            await this.fetchTickets();
            return;
          }
          
          // Wait before next poll
          await new Promise(resolve => setTimeout(resolve, pollInterval));
          attempts++;
          
        } catch (error) {
          console.error('Error polling for classification:', error);
          break;
        }
      }
      
      // If we reach here, classification took too long or failed
      console.warn('Classification polling timeout for ticket:', ticketId);
      await this.fetchTickets(); // Refresh anyway to show current state
    },

    async updateTicketCategory(ticketId, category) {
      try {
        const response = await fetch(`/api/tickets/${ticketId}`, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
          },
          body: JSON.stringify({ 
            category: category || null,
            manually_categorized: true 
          })
        });

        if (!response.ok) throw new Error('Failed to update category');

        // Update the local ticket data immediately for better UX
        const ticket = this.tickets.find(t => t.id === ticketId);
        if (ticket) {
          ticket.category = category || null;
          ticket.manually_categorized = true;
        }

      } catch (error) {
        console.error('Error updating ticket category:', error);
        alert('Failed to update category');
        // Refresh to get the correct state
        await this.fetchTickets();
      }
    },
    
    async createTicket() {
      this.validationErrors = {};
      
      // Basic validation
      if (!this.newTicket.subject.trim()) {
        this.validationErrors.subject = 'Subject is required';
      }
      if (!this.newTicket.body.trim()) {
        this.validationErrors.body = 'Body is required';
      }
      
      if (Object.keys(this.validationErrors).length > 0) return;
      
      try {
        this.submitting = true;
        
        const response = await fetch('/api/tickets', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
          },
          body: JSON.stringify(this.newTicket)
        });
        
        if (!response.ok) {
          const errorData = await response.json();
          if (errorData.errors) {
            this.validationErrors = errorData.errors;
            return;
          }
          throw new Error('Failed to create ticket');
        }
        
        // Reset form and close modal
        this.newTicket = { subject: '', body: '', status: 'open' };
        this.showNewTicketModal = false;
        
        // Refresh tickets
        await this.fetchTickets();
        
      } catch (error) {
        console.error('Error creating ticket:', error);
        alert('Failed to create ticket');
      } finally {
        this.submitting = false;
      }
    },
    
    viewTicket(ticketId) {
      this.$router.push(`/tickets/${ticketId}`);
    },
    
    exportCSV() {
      const headers = ['ID', 'Subject', 'Status', 'Category', 'Confidence', 'Created At'];
      const csvData = [
        headers,
        ...this.tickets.map(ticket => [
          ticket.id,
          `"${ticket.subject.replace(/"/g, '""')}"`,
          ticket.status,
          ticket.category || '',
          ticket.confidence || '',
          ticket.created_at
        ])
      ];
      
      const csvContent = csvData.map(row => row.join(',')).join('\n');
      const blob = new Blob([csvContent], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      
      const a = document.createElement('a');
      a.href = url;
      a.download = `tickets-${new Date().toISOString().split('T')[0]}.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
    },
    
    getStatusLabel(status) {
      const labels = {
        open: 'Open',
        in_progress: 'In Progress',
        resolved: 'Resolved',
        closed: 'Closed'
      };
      return labels[status] || status;
    },
    
    getCategoryLabel(category) {
      const labels = {
        technical: 'Technical Support',
        billing: 'Billing & Payment',
        account: 'Account Management',
        feature_request: 'Feature Request',
        bug_report: 'Bug Report',
        general: 'General Inquiry'
      };
      return labels[category] || category;
    }
  }
}
</script>
