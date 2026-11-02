'use client';

import { useState, useEffect, useMemo } from 'react';
import {
  Newspaper,
  Plus,
  Search,
  Sparkles,
  TrendingUp,
  Clock,
  Eye,
  Edit2,
  Trash2,
  ExternalLink,
  Check,
  AlertCircle,
  X,
  FileText,
  Table,
  Heading,
  HelpCircle,
  Upload,
  Globe,
  Tag
} from 'lucide-react';

interface NewsItem {
  id: string;
  title: string;
  slug: string;
  category: string;
  imageUrl: string;
  tldr: string[];
  content: string;
  readTime: string;
  author: string;
  publishedAt: string;
  updatedAt: string;
  status: 'draft' | 'published';
  isTrending?: boolean;
  views: number;
  ctaType: string;
  seoTitle?: string;
  metaDescription?: string;
  focusKeyword?: string;
  canonicalUrl?: string;
}

const CATEGORIES = [
  'Loans & EMIs',
  'CIBIL & Credit',
  'Banking & RBI',
  'Taxes & MSME',
  'BFS Official'
];

const CTA_OPTIONS = [
  { value: 'home-loan', label: 'Home Loan & Balance Transfer (7.15%* ROI)' },
  { value: 'business-loan', label: 'Unsecured Business Loan (Up to ₹1 Cr)' },
  { value: 'personal-loan', label: 'Personal Loan (Instant Approval)' },
  { value: 'cibil-check', label: 'CIBIL Credit Desk & Health Consultation' },
  { value: 'general', label: 'General Financial Advisory' }
];

export function NewsManager() {
  const [articles, setArticles] = useState<NewsItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('All');
  const [selectedStatus, setSelectedStatus] = useState<'all' | 'published' | 'draft'>('all');
  
  // Modal State
  const [modalOpen, setModalOpen] = useState(false);
  const [editId, setEditId] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');
  const [successMsg, setSuccessMsg] = useState('');

  // Form Fields
  const [title, setTitle] = useState('');
  const [category, setCategory] = useState('Banking & RBI');
  const [author, setAuthor] = useState('Adv. Praveen Bhardwaj');
  const [imageUrl, setImageUrl] = useState('');
  const [tldrPoints, setTldrPoints] = useState<string[]>(['']);
  const [content, setContent] = useState('');
  const [ctaType, setCtaType] = useState('home-loan');
  const [isTrending, setIsTrending] = useState(false);
  const [status, setStatus] = useState<'published' | 'draft'>('published');
  const [seoTitle, setSeoTitle] = useState('');
  const [metaDescription, setMetaDescription] = useState('');
  const [focusKeyword, setFocusKeyword] = useState('');
  const [showSeoDrawer, setShowSeoDrawer] = useState(false);

  // Fetch articles from Website API
  const fetchArticles = async () => {
    setLoading(true);
    try {
      // In production or local dev, try public website URL or relative
      const targetUrl = process.env.NEXT_PUBLIC_WEBSITE_URL 
        ? `${process.env.NEXT_PUBLIC_WEBSITE_URL}/api/news?status=all`
        : 'https://bfsfin.com/api/news?status=all';

      const res = await fetch(targetUrl);
      if (res.ok) {
        const json = await res.json();
        setArticles(json.data || []);
      } else {
        // Fallback to local port 3000 if running locally
        const fallbackRes = await fetch('http://localhost:3000/api/news?status=all');
        if (fallbackRes.ok) {
          const json = await fallbackRes.json();
          setArticles(json.data || []);
        }
      }
    } catch (err) {
      console.warn('Could not connect to live website API directly, attempting localhost:3000:', err);
      try {
        const fallbackRes = await fetch('http://localhost:3000/api/news?status=all');
        if (fallbackRes.ok) {
          const json = await fallbackRes.json();
          setArticles(json.data || []);
        }
      } catch (e) {
        console.error('All fetch attempts failed:', e);
      }
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchArticles();
  }, []);

  // Filtered Articles
  const filtered = useMemo(() => {
    return articles.filter(a => {
      const matchCat = selectedCategory === 'All' || a.category === selectedCategory;
      const matchStat = selectedStatus === 'all' || a.status === selectedStatus;
      const q = search.toLowerCase().trim();
      const matchSearch =
        !q ||
        a.title.toLowerCase().includes(q) ||
        a.category.toLowerCase().includes(q) ||
        (a.tldr && a.tldr.some(t => t.toLowerCase().includes(q)));
      return matchCat && matchStat && matchSearch;
    });
  }, [articles, selectedCategory, selectedStatus, search]);

  // Stats
  const stats = useMemo(() => {
    const total = articles.length;
    const published = articles.filter(a => a.status === 'published').length;
    const drafts = articles.filter(a => a.status === 'draft').length;
    const views = articles.reduce((acc, a) => acc + (a.views || 0), 0);
    return { total, published, drafts, views };
  }, [articles]);

  const resetForm = () => {
    setEditId(null);
    setTitle('');
    setCategory('Banking & RBI');
    setAuthor('Adv. Praveen Bhardwaj');
    setImageUrl('');
    setTldrPoints(['']);
    setContent('');
    setCtaType('home-loan');
    setIsTrending(false);
    setStatus('published');
    setSeoTitle('');
    setMetaDescription('');
    setFocusKeyword('');
    setShowSeoDrawer(false);
    setErrorMsg('');
  };

  const openCreateModal = () => {
    resetForm();
    setModalOpen(true);
  };

  const openEditModal = (item: NewsItem) => {
    setEditId(item.id);
    setTitle(item.title);
    setCategory(item.category);
    setAuthor(item.author);
    setImageUrl(item.imageUrl);
    setTldrPoints(item.tldr && item.tldr.length > 0 ? item.tldr : ['']);
    setContent(item.content);
    setCtaType(item.ctaType || 'home-loan');
    setIsTrending(Boolean(item.isTrending));
    setStatus(item.status);
    setSeoTitle(item.seoTitle || '');
    setMetaDescription(item.metaDescription || '');
    setFocusKeyword(item.focusKeyword || '');
    setShowSeoDrawer(Boolean(item.seoTitle || item.metaDescription));
    setErrorMsg('');
    setModalOpen(true);
  };

  // Automated WebP Image Compression on client side
  const handleImageFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = event => {
      const img = new Image();
      img.onload = () => {
        const canvas = document.createElement('canvas');
        let width = img.width;
        let height = img.height;
        const maxDimension = 1200;
        if (width > maxDimension || height > maxDimension) {
          if (width > height) {
            height = Math.round((height * maxDimension) / width);
            width = maxDimension;
          } else {
            width = Math.round((width * maxDimension) / height);
            height = maxDimension;
          }
        }
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        if (ctx) {
          ctx.drawImage(img, 0, 0, width, height);
          const webpDataUrl = canvas.toDataURL('image/webp', 0.82);
          setImageUrl(webpDataUrl);
        }
      };
      img.src = event.target?.result as string;
    };
    reader.readAsDataURL(file);
  };

  // TLDR bullet point handlers
  const handleAddTldrPoint = () => {
    setTldrPoints([...tldrPoints, '']);
  };

  const handleUpdateTldrPoint = (index: number, val: string) => {
    const updated = [...tldrPoints];
    updated[index] = val;
    setTldrPoints(updated);
  };

  const handleRemoveTldrPoint = (index: number) => {
    if (tldrPoints.length === 1) {
      setTldrPoints(['']);
      return;
    }
    setTldrPoints(tldrPoints.filter((_, i) => i !== index));
  };

  // Markdown / HTML snippet inserters
  const insertSnippet = (snippet: string) => {
    setContent(prev => `${prev}\n${snippet}\n`);
  };

  // Save handler
  const handleSave = async (submitStatus: 'published' | 'draft') => {
    if (!title.trim()) {
      setErrorMsg('Please enter a headline for this article.');
      return;
    }
    if (!content.trim()) {
      setErrorMsg('Please enter article content.');
      return;
    }

    setSubmitting(true);
    setErrorMsg('');

    const payload = {
      id: editId,
      title: title.trim(),
      category,
      author,
      imageUrl: imageUrl.trim() || 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?auto=format&fit=crop&w=1200&q=80',
      tldr: tldrPoints.filter(p => p.trim().length > 0),
      content: content.trim(),
      ctaType,
      isTrending,
      status: submitStatus,
      seoTitle: seoTitle.trim() || title.trim(),
      metaDescription: metaDescription.trim() || tldrPoints[0] || title.trim(),
      focusKeyword: focusKeyword.trim()
    };

    try {
      const baseUrl = process.env.NEXT_PUBLIC_WEBSITE_URL || 'https://bfsfin.com';
      const url = `${baseUrl}/api/news`;
      const method = editId ? 'PUT' : 'POST';

      let res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (!res.ok) {
        // Fallback to local 3000 if on local
        res = await fetch(`http://localhost:3000/api/news`, {
          method,
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
      }

      if (res.ok) {
        setSuccessMsg(editId ? 'Article updated successfully!' : 'Article published successfully to website!');
        setTimeout(() => setSuccessMsg(''), 4000);
        setModalOpen(false);
        resetForm();
        fetchArticles();
      } else {
        const data = await res.json();
        setErrorMsg(data.error || 'Failed to save news article.');
      }
    } catch (err: any) {
      setErrorMsg(err.message || 'Network error saving article.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async (id: string, articleTitle: string) => {
    if (!confirm(`Are you sure you want to permanently delete: "${articleTitle}"?`)) return;

    try {
      const baseUrl = process.env.NEXT_PUBLIC_WEBSITE_URL || 'https://bfsfin.com';
      let res = await fetch(`${baseUrl}/api/news?id=${id}`, { method: 'DELETE' });
      if (!res.ok) {
        res = await fetch(`http://localhost:3000/api/news?id=${id}`, { method: 'DELETE' });
      }
      if (res.ok) {
        setSuccessMsg('Article deleted.');
        setTimeout(() => setSuccessMsg(''), 3000);
        fetchArticles();
      } else {
        alert('Could not delete article.');
      }
    } catch (e) {
      console.error(e);
      alert('Error deleting article.');
    }
  };

  return (
    <div className="space-y-6">
      {/* HEADER & TOP STATS */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold tracking-tight text-foreground flex items-center gap-2">
            <Newspaper className="h-5 w-5 text-primary" />
            Website Financial News & Alerts Desk
          </h2>
          <p className="text-xs text-muted-foreground mt-0.5">
            Create, manage and publish verified financial news, rate alerts, and regulatory updates directly onto <strong className="text-foreground">bfsfin.com/news</strong>.
          </p>
        </div>

        <button
          onClick={openCreateModal}
          className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary text-primary-foreground font-semibold text-xs shadow hover:opacity-90 transition-opacity"
        >
          <Plus className="h-4 w-4" />
          Publish New Article
        </button>
      </div>

      {successMsg && (
        <div className="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center gap-2">
          <Check className="h-4 w-4" /> {successMsg}
        </div>
      )}

      {/* STATS CARDS */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="p-4 rounded-xl border border-border bg-card">
          <p className="text-[11px] font-semibold text-muted-foreground uppercase">Total Articles</p>
          <p className="text-2xl font-extrabold text-foreground mt-1">{stats.total}</p>
        </div>
        <div className="p-4 rounded-xl border border-border bg-card">
          <p className="text-[11px] font-semibold text-emerald-500 uppercase">Live Published</p>
          <p className="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{stats.published}</p>
        </div>
        <div className="p-4 rounded-xl border border-border bg-card">
          <p className="text-[11px] font-semibold text-amber-500 uppercase">Drafts</p>
          <p className="text-2xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{stats.drafts}</p>
        </div>
        <div className="p-4 rounded-xl border border-border bg-card">
          <p className="text-[11px] font-semibold text-primary uppercase">Estimated Readers</p>
          <p className="text-2xl font-extrabold text-primary mt-1">{stats.views.toLocaleString()}</p>
        </div>
      </div>

      {/* FILTER & SEARCH STRIP */}
      <div className="p-3 rounded-xl border border-border bg-card flex flex-col md:flex-row gap-3 items-center justify-between">
        <div className="flex items-center gap-2 w-full md:w-auto overflow-x-auto pb-1 md:pb-0 scrollbar-none">
          <span className="text-xs text-muted-foreground font-semibold shrink-0">Filter:</span>
          {['All', ...CATEGORIES].map(cat => (
            <button
              key={cat}
              onClick={() => setSelectedCategory(cat)}
              className={`px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors ${
                selectedCategory === cat
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-muted text-muted-foreground hover:text-foreground'
              }`}
            >
              {cat}
            </button>
          ))}
        </div>

        <div className="flex items-center gap-2 w-full md:w-auto shrink-0">
          <div className="relative w-full md:w-60">
            <Search className="h-3.5 w-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
            <input
              type="text"
              placeholder="Search headline or keyword..."
              value={search}
              onChange={e => setSearch(e.target.value)}
              className="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg bg-muted border border-border text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
            />
          </div>

          <select
            value={selectedStatus}
            onChange={e => setSelectedStatus(e.target.value as any)}
            className="px-2.5 py-1.5 text-xs rounded-lg bg-muted border border-border text-foreground focus:outline-none"
          >
            <option value="all">All Status</option>
            <option value="published">Published</option>
            <option value="draft">Drafts</option>
          </select>
        </div>
      </div>

      {/* ARTICLES TABLE */}
      <div className="rounded-xl border border-border bg-card overflow-hidden">
        {loading ? (
          <div className="p-12 text-center text-xs text-muted-foreground">Loading news articles...</div>
        ) : filtered.length === 0 ? (
          <div className="p-12 text-center text-xs text-muted-foreground">
            No articles match the current filter. Click "Publish New Article" above to create one.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-muted text-muted-foreground font-semibold border-b border-border">
                <tr>
                  <th className="p-3 w-16">Image</th>
                  <th className="p-3">Headline & Summary</th>
                  <th className="p-3">Category</th>
                  <th className="p-3">Status</th>
                  <th className="p-3">Date</th>
                  <th className="p-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {filtered.map(item => (
                  <tr key={item.id} className="hover:bg-muted/40 transition-colors">
                    <td className="p-3">
                      <div className="w-14 h-10 rounded-lg overflow-hidden border border-border bg-muted">
                        <img src={item.imageUrl} alt="" className="w-full h-full object-cover" />
                      </div>
                    </td>
                    <td className="p-3 max-w-md">
                      <div className="flex items-center gap-1.5 font-bold text-foreground">
                        {item.isTrending && (
                          <span className="px-1.5 py-0.5 rounded text-[10px] bg-rose-500/20 text-rose-500 font-extrabold flex items-center gap-0.5">
                            <TrendingUp className="w-2.5 h-2.5" /> TRENDING
                          </span>
                        )}
                        <span className="line-clamp-1">{item.title}</span>
                      </div>
                      <p className="text-[11px] text-muted-foreground line-clamp-1 mt-0.5">
                        {item.tldr && item.tldr[0] ? item.tldr[0] : item.slug}
                      </p>
                    </td>
                    <td className="p-3 whitespace-nowrap">
                      <span className="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-primary/10 text-primary border border-primary/20">
                        {item.category}
                      </span>
                    </td>
                    <td className="p-3 whitespace-nowrap">
                      <span
                        className={`px-2 py-0.5 rounded-full text-[10px] font-extrabold ${
                          item.status === 'published'
                            ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20'
                            : 'bg-amber-500/10 text-amber-500 border border-amber-500/20'
                        }`}
                      >
                        {item.status.toUpperCase()}
                      </span>
                    </td>
                    <td className="p-3 whitespace-nowrap text-muted-foreground text-[11px]">
                      {new Date(item.publishedAt).toLocaleDateString('en-IN', {
                        day: 'numeric',
                        month: 'short'
                      })}
                    </td>
                    <td className="p-3 text-right whitespace-nowrap">
                      <div className="inline-flex items-center gap-1">
                        <a
                          href={`https://bfsfin.com/news/${item.slug}`}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="p-1.5 rounded-md hover:bg-muted text-muted-foreground hover:text-foreground"
                          title="View live on website"
                        >
                          <ExternalLink className="h-3.5 w-3.5" />
                        </a>
                        <button
                          onClick={() => openEditModal(item)}
                          className="p-1.5 rounded-md hover:bg-muted text-muted-foreground hover:text-foreground"
                          title="Edit article"
                        >
                          <Edit2 className="h-3.5 w-3.5" />
                        </button>
                        <button
                          onClick={() => handleDelete(item.id, item.title)}
                          className="p-1.5 rounded-md hover:bg-rose-500/10 text-muted-foreground hover:text-rose-500"
                          title="Delete article"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* CREATE / EDIT MODAL */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-sm overflow-y-auto">
          <div className="w-full max-w-3xl rounded-2xl border border-border bg-card p-6 shadow-2xl space-y-5 my-8 max-h-[90vh] overflow-y-auto">
            {/* Header */}
            <div className="flex items-center justify-between pb-3 border-b border-border">
              <div>
                <h3 className="text-lg font-bold text-foreground">
                  {editId ? 'Edit Financial Article' : 'Publish New Financial Update to Website'}
                </h3>
                <p className="text-xs text-muted-foreground">
                  Changes sync immediately to https://bfsfin.com/news with automatic Schema & Sitemap generation.
                </p>
              </div>
              <button
                onClick={() => setModalOpen(false)}
                className="p-1.5 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {errorMsg && (
              <div className="p-3 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-500 text-xs font-semibold flex items-center gap-2">
                <AlertCircle className="h-4 w-4 shrink-0" />
                {errorMsg}
              </div>
            )}

            {/* Form */}
            <div className="space-y-4 text-xs">
              {/* Title */}
              <div>
                <label className="block font-bold text-foreground mb-1">Article Headline *</label>
                <input
                  type="text"
                  placeholder="e.g. RBI MPC Keeps Repo Rate Unchanged at 6.50%: What It Means for Your Loan EMIs"
                  value={title}
                  onChange={e => setTitle(e.target.value)}
                  className="w-full px-3 py-2 rounded-xl bg-muted border border-border text-foreground font-semibold text-sm focus:outline-none focus:ring-1 focus:ring-primary"
                />
              </div>

              {/* Category, Author, CTA */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                  <label className="block font-semibold text-muted-foreground mb-1">Category</label>
                  <select
                    value={category}
                    onChange={e => setCategory(e.target.value)}
                    className="w-full px-3 py-2 rounded-lg bg-muted border border-border text-foreground"
                  >
                    {CATEGORIES.map(c => (
                      <option key={c} value={c}>{c}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block font-semibold text-muted-foreground mb-1">Author</label>
                  <select
                    value={author}
                    onChange={e => setAuthor(e.target.value)}
                    className="w-full px-3 py-2 rounded-lg bg-muted border border-border text-foreground"
                  >
                    <option value="Adv. Praveen Bhardwaj">Adv. Praveen Bhardwaj (MD)</option>
                    <option value="BFS Research Desk">BFS Research Desk</option>
                    <option value="BFS Editorial Team">BFS Editorial Team</option>
                  </select>
                </div>

                <div>
                  <label className="block font-semibold text-muted-foreground mb-1">Contextual In-Article CTA</label>
                  <select
                    value={ctaType}
                    onChange={e => setCtaType(e.target.value)}
                    className="w-full px-3 py-2 rounded-lg bg-muted border border-border text-foreground"
                  >
                    {CTA_OPTIONS.map(opt => (
                      <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Image URL & WebP Upload */}
              <div>
                <label className="block font-semibold text-muted-foreground mb-1">Featured Image (Auto WebP Compressed)</label>
                <div className="flex gap-2">
                  <input
                    type="text"
                    placeholder="https://... or upload below"
                    value={imageUrl}
                    onChange={e => setImageUrl(e.target.value)}
                    className="flex-grow px-3 py-2 rounded-lg bg-muted border border-border text-foreground text-xs"
                  />
                  <label className="px-3 py-2 rounded-lg bg-muted hover:bg-muted/80 border border-border font-semibold text-foreground cursor-pointer flex items-center gap-1.5 shrink-0">
                    <Upload className="w-3.5 h-3.5 text-primary" />
                    <span>Upload & Compress</span>
                    <input
                      type="file"
                      accept="image/*"
                      onChange={handleImageFileChange}
                      className="hidden"
                    />
                  </label>
                </div>
                {imageUrl && (
                  <div className="mt-2 h-24 w-40 rounded-lg overflow-hidden border border-border bg-muted">
                    <img src={imageUrl} alt="preview" className="w-full h-full object-cover" />
                  </div>
                )}
              </div>

              {/* TL;DR BULLETS (FEATURE FOR BUSY READERS & AI ENGINES) */}
              <div className="p-3.5 rounded-xl border border-primary/20 bg-primary/5 space-y-2">
                <div className="flex items-center justify-between">
                  <label className="font-bold text-foreground flex items-center gap-1.5">
                    <Sparkles className="w-3.5 h-3.5 text-primary" />
                    In a Nutshell (TL;DR Quick Summary Bullets)
                  </label>
                  <button
                    type="button"
                    onClick={handleAddTldrPoint}
                    className="text-[11px] font-bold text-primary hover:underline flex items-center gap-1"
                  >
                    <Plus className="w-3 h-3" /> Add Key Point
                  </button>
                </div>
                <p className="text-[11px] text-muted-foreground">
                  Add 2 to 4 bullet points that summarize the article for mobile readers and AI Answer Engines.
                </p>

                <div className="space-y-2 mt-2">
                  {tldrPoints.map((point, index) => (
                    <div key={index} className="flex gap-2 items-center">
                      <span className="text-xs font-bold text-primary shrink-0">•</span>
                      <input
                        type="text"
                        placeholder={`Key point #${index + 1}...`}
                        value={point}
                        onChange={e => handleUpdateTldrPoint(index, e.target.value)}
                        className="flex-grow px-3 py-1.5 rounded-lg bg-background border border-border text-foreground text-xs"
                      />
                      <button
                        type="button"
                        onClick={() => handleRemoveTldrPoint(index)}
                        className="p-1 rounded text-muted-foreground hover:text-rose-500"
                        title="Remove point"
                      >
                        <X className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  ))}
                </div>
              </div>

              {/* ARTICLE CONTENT (WITH FORMATTING HELPERS) */}
              <div>
                <div className="flex items-center justify-between mb-1.5">
                  <label className="font-bold text-foreground">Article Main Body (HTML / Markdown supported) *</label>
                  {/* Quick Snippet Inserts */}
                  <div className="flex items-center gap-1 text-[10px]">
                    <span className="text-muted-foreground">Quick Insert:</span>
                    <button
                      type="button"
                      onClick={() => insertSnippet('<h2>Subheading Title</h2>')}
                      className="px-2 py-0.5 rounded bg-muted hover:bg-muted/80 text-foreground font-semibold"
                    >
                      H2 Heading
                    </button>
                    <button
                      type="button"
                      onClick={() => insertSnippet('<div class="my-4 p-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 font-medium"><p><strong>Important Note:</strong> Insert key advisory here.</p></div>')}
                      className="px-2 py-0.5 rounded bg-muted hover:bg-muted/80 text-foreground font-semibold"
                    >
                      Note Callout
                    </button>
                    <button
                      type="button"
                      onClick={() => insertSnippet('<table class="w-full border-collapse my-4 text-xs"><thead><tr class="bg-muted"><th>Bank</th><th>Rate</th><th>Tenure</th></tr></thead><tbody><tr><td>SBI</td><td>8.50%</td><td>30 Yrs</td></tr></tbody></table>')}
                      className="px-2 py-0.5 rounded bg-muted hover:bg-muted/80 text-foreground font-semibold"
                    >
                      Comparison Table
                    </button>
                  </div>
                </div>

                <textarea
                  rows={9}
                  placeholder="<p>Write your detailed article paragraphs here...</p>"
                  value={content}
                  onChange={e => setContent(e.target.value)}
                  className="w-full px-3 py-2.5 rounded-xl bg-muted border border-border text-foreground font-mono text-xs leading-relaxed focus:outline-none focus:ring-1 focus:ring-primary"
                />
              </div>

              {/* SEO & META CONTROLS ACCORDION */}
              <div className="rounded-xl border border-border bg-muted/40 p-3">
                <button
                  type="button"
                  onClick={() => setShowSeoDrawer(!showSeoDrawer)}
                  className="w-full flex items-center justify-between font-bold text-foreground text-xs"
                >
                  <span className="flex items-center gap-1.5">
                    <Globe className="w-4 h-4 text-primary" />
                    SEO & Meta Tags Customization (Google & AEO)
                  </span>
                  <span className="text-[11px] text-primary">{showSeoDrawer ? 'Hide ▲' : 'Customize ▼'}</span>
                </button>

                {showSeoDrawer && (
                  <div className="mt-3 space-y-3 pt-3 border-t border-border">
                    <div>
                      <div className="flex justify-between text-[11px] mb-1">
                        <span className="text-muted-foreground font-semibold">Custom Meta Title</span>
                        <span className="text-muted-foreground">{seoTitle.length || title.length} / 60 chars</span>
                      </div>
                      <input
                        type="text"
                        placeholder="Leave blank to use main headline"
                        value={seoTitle}
                        onChange={e => setSeoTitle(e.target.value)}
                        className="w-full px-3 py-1.5 rounded-lg bg-background border border-border text-foreground"
                      />
                    </div>

                    <div>
                      <div className="flex justify-between text-[11px] mb-1">
                        <span className="text-muted-foreground font-semibold">Meta Description</span>
                        <span className="text-muted-foreground">{metaDescription.length || (tldrPoints[0] || '').length} / 160 chars</span>
                      </div>
                      <textarea
                        rows={2}
                        placeholder="Leave blank to use first TL;DR point"
                        value={metaDescription}
                        onChange={e => setMetaDescription(e.target.value)}
                        className="w-full px-3 py-1.5 rounded-lg bg-background border border-border text-foreground"
                      />
                    </div>

                    <div>
                      <span className="text-muted-foreground font-semibold text-[11px] block mb-1">Focus Keyword</span>
                      <input
                        type="text"
                        placeholder="e.g. rbi repo rate, home loan emi agra"
                        value={focusKeyword}
                        onChange={e => setFocusKeyword(e.target.value)}
                        className="w-full px-3 py-1.5 rounded-lg bg-background border border-border text-foreground"
                      />
                    </div>
                  </div>
                )}
              </div>

              {/* TRENDING HERO CHECKBOX */}
              <div className="flex items-center gap-2 pt-1">
                <input
                  type="checkbox"
                  id="trendingCheck"
                  checked={isTrending}
                  onChange={e => setIsTrending(e.target.checked)}
                  className="rounded border-border text-primary focus:ring-primary h-4 w-4"
                />
                <label htmlFor="trendingCheck" className="text-xs font-bold text-foreground cursor-pointer flex items-center gap-1.5">
                  <TrendingUp className="w-3.5 h-3.5 text-rose-500" />
                  Highlight as Breaking / Trending Banner on Homepage & News Hub
                </label>
              </div>
            </div>

            {/* Modal Actions */}
            <div className="flex items-center justify-between pt-4 border-t border-border">
              <button
                type="button"
                onClick={() => setModalOpen(false)}
                className="px-4 py-2 rounded-xl text-xs font-semibold text-muted-foreground hover:bg-muted transition-colors"
              >
                Cancel
              </button>

              <div className="flex items-center gap-2">
                <button
                  type="button"
                  disabled={submitting}
                  onClick={() => handleSave('draft')}
                  className="px-4 py-2 rounded-xl text-xs font-bold bg-muted hover:bg-muted/80 text-foreground border border-border transition-colors disabled:opacity-50"
                >
                  Save as Draft
                </button>
                <button
                  type="button"
                  disabled={submitting}
                  onClick={() => handleSave('published')}
                  className="px-5 py-2 rounded-xl text-xs font-bold bg-primary hover:opacity-90 text-primary-foreground shadow-md transition-opacity disabled:opacity-50 flex items-center gap-1.5"
                >
                  {submitting ? 'Saving...' : 'Publish to Website Live'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
