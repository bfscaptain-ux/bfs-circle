/**
 * Starter flow templates.
 *
 * Three pre-canned flows users can clone with one click instead of
 * building from scratch. Each template is a plain JS object describing
 * the same shape `/api/flows` PUT accepts — name, trigger config,
 * entry_node_id, fallback_policy, nodes[] — keyed by a stable
 * `slug`.
 *
 * The clone path (`/api/flows` POST with `template_slug`) creates a
 * NEW flow_row + flow_nodes rows for the user. `node_key`s are kept
 * verbatim (they're stable strings, not UUIDs, so cloning never
 * needs to rewrite edge references).
 *
 * Choosing a single static module over a DB-backed gallery for v1
 * because: (a) the set is small and changes with code releases, not
 * data; (b) keeps templates portable across self-hosted instances
 * without migrations; (c) editing in source is the lowest-friction
 * way to add the next template.
 */

import type {
  CollectInputNodeConfig,
  ConditionNodeConfig,
  HandoffNodeConfig,
  KeywordTriggerConfig,
  SendButtonsNodeConfig,
  SendListNodeConfig,
  SendMessageNodeConfig,
  StartNodeConfig,
} from "./types";

export type FlowTemplateNodeType =
  | "start"
  | "send_message"
  | "send_buttons"
  | "send_list"
  | "collect_input"
  | "condition"
  | "set_tag"
  | "handoff"
  | "end";

export interface FlowTemplateNode {
  node_key: string;
  node_type: FlowTemplateNodeType;
  config:
    | StartNodeConfig
    | SendMessageNodeConfig
    | SendButtonsNodeConfig
    | SendListNodeConfig
    | CollectInputNodeConfig
    | ConditionNodeConfig
    | HandoffNodeConfig
    | Record<string, unknown>;
}

export interface FlowTemplate {
  slug: string;
  name: string;
  description: string;
  /** Used by the gallery to surface a relevant icon. lucide-react name. */
  icon: "MessageSquare" | "HelpCircle" | "UserPlus";
  trigger_type: "keyword" | "first_inbound_message" | "manual";
  trigger_config: KeywordTriggerConfig | Record<string, unknown>;
  entry_node_id: string;
  nodes: FlowTemplateNode[];
}

// ============================================================
// 1. Welcome menu — the example from the owner's brief
// ============================================================
const WELCOME_MENU: FlowTemplate = {
  slug: "welcome_menu",
  name: "Welcome menu",
  description:
    "Greet customers who type a keyword and route them to the right agent based on whether they're new or existing.",
  icon: "MessageSquare",
  trigger_type: "keyword",
  trigger_config: { keywords: ["support", "help", "hi"], match_type: "contains" },
  entry_node_id: "start",
  nodes: [
    {
      node_key: "start",
      node_type: "start",
      config: { next_node_key: "welcome" },
    },
    {
      node_key: "welcome",
      node_type: "send_buttons",
      config: {
        text: "Hi! 👋 Welcome to support. Are you an existing customer or new here?",
        footer_text: "Tap a button below to continue.",
        buttons: [
          {
            reply_id: "existing",
            title: "Existing customer",
            next_node_key: "existing_handoff",
          },
          {
            reply_id: "new",
            title: "New customer",
            next_node_key: "new_handoff",
          },
        ],
      } as SendButtonsNodeConfig,
    },
    {
      node_key: "existing_handoff",
      node_type: "handoff",
      config: {
        note: "Existing customer needs assistance — please check account history before replying.",
      } as HandoffNodeConfig,
    },
    {
      node_key: "new_handoff",
      node_type: "handoff",
      config: {
        note: "New customer — share pricing + onboarding link.",
      } as HandoffNodeConfig,
    },
  ],
};

// ============================================================
// 2. FAQ bot — list-message answers, fully automated
// ============================================================
const FAQ_BOT: FlowTemplate = {
  slug: "faq_bot",
  name: "FAQ bot",
  description:
    "Answer common questions automatically. Customer picks a topic from a list; the bot replies with the answer and ends.",
  icon: "HelpCircle",
  trigger_type: "keyword",
  trigger_config: {
    keywords: ["faq", "question", "info"],
    match_type: "contains",
  },
  entry_node_id: "start",
  nodes: [
    {
      node_key: "start",
      node_type: "start",
      config: { next_node_key: "topics" },
    },
    {
      node_key: "topics",
      node_type: "send_list",
      config: {
        text: "What can I help you with?",
        button_label: "View topics",
        sections: [
          {
            title: "Common questions",
            rows: [
              {
                reply_id: "hours",
                title: "Opening hours",
                next_node_key: "answer_hours",
              },
              {
                reply_id: "pricing",
                title: "Pricing",
                next_node_key: "answer_pricing",
              },
              {
                reply_id: "refunds",
                title: "Refund policy",
                next_node_key: "answer_refunds",
              },
            ],
          },
          {
            title: "Other",
            rows: [
              {
                reply_id: "human",
                title: "Talk to a human",
                next_node_key: "human_handoff",
              },
            ],
          },
        ],
      } as SendListNodeConfig,
    },
    {
      node_key: "answer_hours",
      node_type: "send_message",
      config: {
        text: "We're open Mon–Fri, 9am–6pm local time. Weekend support is limited to urgent issues.",
        next_node_key: "end",
      } as SendMessageNodeConfig,
    },
    {
      node_key: "answer_pricing",
      node_type: "send_message",
      config: {
        text: "Our pricing starts at $9/mo. Visit https://example.com/pricing for the full breakdown.",
        next_node_key: "end",
      } as SendMessageNodeConfig,
    },
    {
      node_key: "answer_refunds",
      node_type: "send_message",
      config: {
        text: "Refunds are honored within 30 days of purchase. Reply with your order number and we'll process it.",
        next_node_key: "end",
      } as SendMessageNodeConfig,
    },
    {
      node_key: "human_handoff",
      node_type: "handoff",
      config: {
        note: "Customer asked to talk to a human from the FAQ bot.",
      } as HandoffNodeConfig,
    },
    {
      node_key: "end",
      node_type: "end",
      config: {},
    },
  ],
};

// ============================================================
// 3. Lead capture — collect_input chain, ends in a handoff
// ============================================================
const LEAD_CAPTURE: FlowTemplate = {
  slug: "lead_capture",
  name: "Lead capture",
  description:
    "Greet first-time inbounds, capture name + email + company, then hand off to sales with the answers in the note.",
  icon: "UserPlus",
  trigger_type: "first_inbound_message",
  trigger_config: {},
  entry_node_id: "start",
  nodes: [
    {
      node_key: "start",
      node_type: "start",
      config: { next_node_key: "intro" },
    },
    {
      node_key: "intro",
      node_type: "send_message",
      config: {
        text: "Welcome! 👋 I'll ask a few quick questions so we can get you to the right person.",
        next_node_key: "ask_name",
      } as SendMessageNodeConfig,
    },
    {
      node_key: "ask_name",
      node_type: "collect_input",
      config: {
        prompt_text: "What's your name?",
        var_key: "name",
        next_node_key: "ask_email",
      } as CollectInputNodeConfig,
    },
    {
      node_key: "ask_email",
      node_type: "collect_input",
      config: {
        prompt_text: "Thanks {{vars.name}}! What's your work email?",
        var_key: "email",
        next_node_key: "ask_company",
      } as CollectInputNodeConfig,
    },
    {
      node_key: "ask_company",
      node_type: "collect_input",
      config: {
        prompt_text: "Almost done — what's your company name?",
        var_key: "company",
        next_node_key: "handoff",
      } as CollectInputNodeConfig,
    },
    {
      node_key: "handoff",
      node_type: "handoff",
      config: {
        note: "New lead — name={{vars.name}}, email={{vars.email}}, company={{vars.company}}.",
      } as HandoffNodeConfig,
    },
  ],
};

// ============================================================
// Registry
// ============================================================

// ============================================================
// 4. Bhardwaj Financial Services — Direct Loan & BT Qualification
// ============================================================
const BFS_LOAN_QUALIFICATION: FlowTemplate = {
  slug: "bfs_loan_qualification",
  name: "BFS Loan & BT Qualification",
  description:
    "Bhardwaj Financial Services direct-lender greeting with Interested/Not Interested buttons, all Loan types + Balance Transfer (BT) branching, requirement capture, and city qualification.",
  icon: "UserPlus",
  trigger_type: "first_inbound_message",
  trigger_config: {},
  entry_node_id: "start",
  nodes: [
    {
      node_key: "start",
      node_type: "start",
      config: { next_node_key: "welcome_interest" },
    },
    {
      node_key: "welcome_interest",
      node_type: "send_buttons",
      config: {
        header_text: "Bhardwaj Financial Services",
        text:
          "Namaste {{contact.first_name}} ji! 🙏\n\n" +
          "*Bhardwaj Financial Services* mein aapka swagat hai. Hum apne Credit Desk ke zariye sabhi prakar ke Loans aur Balance Transfer (BT) ki direct suvidha dete hain — bina kisi middleman ke, fast processing aur best interest rates ke saath.\n\n" +
          "Kya aap naye Loan ya Balance Transfer (BT) ke liye jankari lena chahte hain?",
        footer_text: "Direct Credit Desk • Agra & PAN India",
        buttons: [
          {
            reply_id: "interested",
            title: "Interested",
            next_node_key: "choose_loan_type",
          },
          {
            reply_id: "not_interested",
            title: "Not Interested",
            next_node_key: "not_interested_msg",
          },
        ],
      } as SendButtonsNodeConfig,
    },
    {
      node_key: "not_interested_msg",
      node_type: "send_message",
      config: {
        text:
          "Dhanyavaad {{contact.first_name}} ji! 🙏 Koi baat nahi. Jab bhi aapko future mein kisi bhi Loan ya Balance Transfer ki zaroorat ho, aap humein yahan message kar sakte hain. Aapka din shubh rahe!",
        next_node_key: "end_not_interested",
      } as SendMessageNodeConfig,
    },
    {
      node_key: "end_not_interested",
      node_type: "end",
      config: {},
    },
    {
      node_key: "choose_loan_type",
      node_type: "send_list",
      config: {
        header_text: "Loan & BT Options",
        text:
          "Bahut badhiya {{contact.first_name}} ji! 👍\n\n" +
          "Kripya neeche di gayi list se batayein aapko kis tarah ke Loan ya Balance Transfer (BT) ki zaroorat hai:",
        footer_text: "Tap below to choose",
        button_label: "Select Loan Type",
        sections: [
          {
            title: "Secured & Property Loans",
            rows: [
              {
                reply_id: "home_loan",
                title: "Home Loan",
                description: "Naya ghar kharidne ya ghar banane ke liye",
                next_node_key: "ask_amount",
              },
              {
                reply_id: "lap_loan",
                title: "Loan Against Property",
                description: "Residential ya commercial property par loan",
                next_node_key: "ask_amount",
              },
              {
                reply_id: "topup_loan",
                title: "Top-Up Loan",
                description: "Maujooda loan ke upar extra fund ke liye",
                next_node_key: "ask_amount",
              },
            ],
          },
          {
            title: "Business & Personal",
            rows: [
              {
                reply_id: "business_loan",
                title: "Business Loan",
                description: "Vyapar badhane aur working capital ke liye",
                next_node_key: "ask_amount",
              },
              {
                reply_id: "personal_loan",
                title: "Personal Loan",
                description: "Salaried aur self-employed ke liye quick fund",
                next_node_key: "ask_amount",
              },
              {
                reply_id: "auto_loan",
                title: "Car / Auto Loan",
                description: "Nayi ya purani gaadi ke liye loan",
                next_node_key: "ask_amount",
              },
            ],
          },
          {
            title: "Balance Transfer (BT)",
            rows: [
              {
                reply_id: "balance_transfer",
                title: "Balance Transfer (BT)",
                description: "Chalte loan ko kam byaj rate par shift karein",
                next_node_key: "choose_bt_type",
              },
            ],
          },
        ],
      } as SendListNodeConfig,
    },
    {
      node_key: "choose_bt_type",
      node_type: "send_list",
      config: {
        header_text: "Balance Transfer (BT)",
        text:
          "Bilkul {{contact.first_name}} ji, hum aapka chal raha loan kam interest rate par transfer karne ke saath Top-Up ki suvidha bhi dete hain.\n\n" +
          "Kripya batayein aapka maujooda (BT) loan kis category ka hai?",
        footer_text: "Select BT loan category",
        button_label: "Select BT Loan Type",
        sections: [
          {
            title: "BT Loan Category",
            rows: [
              {
                reply_id: "bt_home",
                title: "Home Loan BT",
                description: "Chal rahe Home Loan ko kam rate par shift karein",
                next_node_key: "ask_bt_details",
              },
              {
                reply_id: "bt_lap",
                title: "LAP / Property BT",
                description: "Loan Against Property ka Balance Transfer + Top-Up",
                next_node_key: "ask_bt_details",
              },
              {
                reply_id: "bt_business",
                title: "Business Loan BT",
                description: "Chal rahe Business Loan ko transfer ya consolidate karein",
                next_node_key: "ask_bt_details",
              },
              {
                reply_id: "bt_personal",
                title: "Personal Loan BT",
                description: "Personal Loan ko kam EMI aur behtar rate par shift karein",
                next_node_key: "ask_bt_details",
              },
            ],
          },
        ],
      } as SendListNodeConfig,
    },
    {
      node_key: "ask_bt_details",
      node_type: "collect_input",
      config: {
        prompt_text:
          "Aapka abhi kitna Balance Transfer (BT) amount bacha hai aur kya aapko saath mein Top-Up bhi chahiye? (Jaise: 25 Lakh BT + 5 Lakh Top-Up)",
        var_key: "loan_amount",
        next_node_key: "ask_work_profile",
      } as CollectInputNodeConfig,
    },
    {
      node_key: "ask_amount",
      node_type: "collect_input",
      config: {
        prompt_text:
          "Shukriya {{contact.first_name}} ji! Aapko lagbhag kitne rupaye ke Loan ki zaroorat hai? (Jaise: 10 Lakh, 25 Lakh, 1 Crore)",
        var_key: "loan_amount",
        next_node_key: "ask_work_profile",
      } as CollectInputNodeConfig,
    },
    {
      node_key: "ask_work_profile",
      node_type: "send_buttons",
      config: {
        text:
          "Note kar liya (Requirement: {{vars.loan_amount}}).\n\n" +
          "Aapka work profile kya hai, taaki humari Credit Team sahi eligibility aur best rate tayyar kar sake?",
        buttons: [
          {
            reply_id: "salaried",
            title: "Salaried (Job)",
            next_node_key: "ask_city",
          },
          {
            reply_id: "business",
            title: "Business / Self-Emp",
            next_node_key: "ask_city",
          },
        ],
      } as SendButtonsNodeConfig,
    },
    {
      node_key: "ask_city",
      node_type: "collect_input",
      config: {
        prompt_text:
          "Aakhri sawal {{contact.first_name}} ji — aap kis shehar (City) se hain aur property/office kis location par hai?",
        var_key: "city",
        next_node_key: "final_confirm",
      } as CollectInputNodeConfig,
    },
    {
      node_key: "final_confirm",
      node_type: "send_message",
      config: {
        text:
          "Dhanyavaad {{contact.first_name}} ji! 🙏 Aapki requirement humare Credit Desk par register ho gayi hai:\n\n" +
          "💰 Requirement: {{vars.loan_amount}}\n" +
          "📍 Location: {{vars.city}}\n\n" +
          "Humare Senior Credit Officer jald hi aapse contact karke direct loan sanction process aur documents ki jankari denge. Agar aapka koi bhi sawal hai (interest rate, ROI, tenure ya documents), toh aap yahan pooch sakte hain!",
        next_node_key: "end_qualified",
      } as SendMessageNodeConfig,
    },
    {
      node_key: "end_qualified",
      node_type: "end",
      config: {},
    },
  ],
};

const TEMPLATES: Record<string, FlowTemplate> = {
  bfs_loan_qualification: BFS_LOAN_QUALIFICATION,
  welcome_menu: WELCOME_MENU,
  faq_bot: FAQ_BOT,
  lead_capture: LEAD_CAPTURE,
};

export function getFlowTemplate(slug: string): FlowTemplate | null {
  return TEMPLATES[slug] ?? null;
}

export function listFlowTemplates(): FlowTemplate[] {
  return Object.values(TEMPLATES);
}
